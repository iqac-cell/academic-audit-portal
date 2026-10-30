<?php

namespace Mmmut\AcademicAudit\Services\Ai;

/** Reads a proof document (PDF / image) and returns values for a parameter's columns. */
interface DocumentExtractor
{
    /**
     * @param  array  $definition  Catalogue parameter definition
     * @return array{data: array<string, mixed>, model: string, input_tokens: int, output_tokens: int}
     *
     * @throws ExtractionException with a message that can be shown to the user
     */
    public function extract(array $definition, string $path, string $mimeType): array;

    /** Checks that the API key / network work. Returns a short status line. */
    public function ping(): string;
}
