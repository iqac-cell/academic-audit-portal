<?php

namespace Mmmut\AcademicAudit\Services\Ai;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;
use Anthropic\RequestOptions;
use GuzzleHttp\Client as GuzzleClient;
use Mmmut\AcademicAudit\Services\SettingsService;

/**
 * Extracts row values from an uploaded certificate / paper / sanction letter with Claude.
 * The response is constrained to a JSON schema built from the parameter's columns, and every
 * value is validated again by the portal before it is put in the form for the user to review.
 */
class ClaudeDocumentExtractor implements DocumentExtractor
{
    private const SYSTEM = <<<'TXT'
You read proof documents (certificates, sanction letters, journal papers, offer letters, event reports) for a university's internal academic audit and fill one row of a data table.
Rules:
- Use only information that is visibly present in the document. If a field is not stated, return null. Never guess or invent values.
- Dates as YYYY-MM-DD. Years as four digits.
- Numbers as plain numbers without currency symbols or commas, in the unit named in the field description (e.g. "INR in lakhs" means 250000 rupees becomes 2.5).
- For Yes/No fields use "Y" or "N", only when the document makes it clear; otherwise null.
- For fields with a fixed list of options, pick the matching option or null.
- Person names as written, without titles unless the title is part of how the name appears on the document.
TXT;

    public function __construct(private SettingsService $settings) {}

    public function extract(array $definition, string $path, string $mimeType): array
    {
        $isPdf = $mimeType === 'application/pdf';
        if (! $isPdf && ! in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])) {
            throw new ExtractionException('Only PDF, JPG, PNG or WEBP files can be read.');
        }
        $source = ['type' => 'base64', 'mediaType' => $mimeType, 'data' => base64_encode(file_get_contents($path))];
        $content = [
            ['type' => $isPdf ? 'document' : 'image', 'source' => $source],
            ['type' => 'text', 'text' => "Fill one row of the table \"{$definition['code']} {$definition['title']}\" from this document."],
        ];

        $model = (string) $this->settings->get('ai.model');
        try {
            $message = $this->client()->beta->messages->create(
                maxTokens: 4000,
                messages: [['role' => 'user', 'content' => $content]],
                model: $model,
                system: self::SYSTEM,
                outputConfig: [
                    'effort' => 'low',
                    'format' => ['type' => 'json_schema', 'schema' => $this->schema($definition)],
                ],
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (AuthenticationException) {
            throw new ExtractionException('The Claude API key is not valid. Ask IQAC to check Settings → AI.');
        } catch (RateLimitException) {
            throw new ExtractionException('The AI service is busy right now. Please try again in a minute.');
        } catch (APIConnectionException) {
            throw new ExtractionException('Could not reach the AI service (api.anthropic.com). Check the server internet / proxy setting.');
        } catch (APIStatusException $e) {
            throw new ExtractionException('The AI service could not read this file ('.$e->getMessage().').');
        }

        if ($message->stopReason === 'refusal') {
            throw new ExtractionException('The AI service declined to read this document. Please fill the row manually.');
        }
        if ($message->stopReason === 'max_tokens') {
            throw new ExtractionException('The document was too long to read completely. Please fill the row manually.');
        }
        $json = null;
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $json = json_decode($block->text, true);
                break;
            }
        }
        if (! is_array($json)) {
            throw new ExtractionException('No readable values were found in the document.');
        }

        return [
            'data' => $json,
            'model' => $message->model,
            'input_tokens' => $message->usage->inputTokens,
            'output_tokens' => $message->usage->outputTokens,
        ];
    }

    public function ping(): string
    {
        try {
            $info = $this->client()->models->retrieve((string) $this->settings->get('ai.model'));
            return "Connected. Model available: {$info->id}.";
        } catch (AuthenticationException) {
            throw new ExtractionException('API key rejected by Anthropic.');
        } catch (APIConnectionException $e) {
            throw new ExtractionException('Cannot reach api.anthropic.com: '.$e->getMessage());
        } catch (APIStatusException $e) {
            throw new ExtractionException($e->getMessage());
        }
    }

    /** JSON schema for one row: every column nullable, typed from the catalogue. */
    public function schema(array $definition): array
    {
        $props = [];
        foreach ($definition['columns'] as $c) {
            $base = match ($c['type']) {
                'number' => ['type' => 'number'],
                'yn' => ['type' => 'string', 'enum' => ['Y', 'N']],
                'select' => ['type' => 'string', 'enum' => array_values($c['options'])],
                'date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                default => ['type' => 'string'],
            };
            $prop = [
                'description' => trim($c['label'].' '.($base['description'] ?? '')),
                'anyOf' => [array_diff_key($base, ['description' => 1]), ['type' => 'null']],
            ];
            $props[$c['key']] = $prop;
        }
        return ['type' => 'object', 'properties' => $props, 'required' => array_keys($props), 'additionalProperties' => false];
    }

    protected function client(): Client
    {
        $key = (string) $this->settings->get('ai.api_key');
        if ($key === '') {
            throw new ExtractionException('No Claude API key saved. Ask IQAC to add it in Settings → AI.');
        }
        $guzzle = ['timeout' => 120, 'connect_timeout' => 15];
        if ($proxy = trim((string) $this->settings->get('ai.proxy'))) {
            $guzzle['proxy'] = $proxy;
        }
        return new Client(
            apiKey: $key,
            baseUrl: 'https://api.anthropic.com',
            requestOptions: RequestOptions::with(maxRetries: 2, transporter: new GuzzleClient($guzzle)),
        );
    }
}
