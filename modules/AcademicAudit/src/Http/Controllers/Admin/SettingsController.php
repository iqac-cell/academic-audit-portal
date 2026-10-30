<?php

namespace Mmmut\AcademicAudit\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mmmut\AcademicAudit\Http\Controllers\Controller;
use Mmmut\AcademicAudit\Services\Ai\DocumentExtractor;
use Mmmut\AcademicAudit\Services\Ai\ExtractionException;
use Mmmut\AcademicAudit\Services\Notifier;
use Mmmut\AcademicAudit\Services\SettingsService;
use Mmmut\AcademicAudit\Support\SettingsRegistry;

class SettingsController extends Controller
{
    public function index(Request $request, SettingsService $settings)
    {
        $this->iqac();
        $group = array_key_exists($request->query('tab'), SettingsRegistry::GROUPS) ? $request->query('tab') : 'general';
        $usage = $group === 'ai'
            ? DB::table('aa_ai_logs')->where('created_at', '>=', now()->subDays(30))
                ->selectRaw('user_id, count(*) as calls, sum(input_tokens) as input_tokens, sum(output_tokens) as output_tokens, sum(case when status = ? then 1 else 0 end) as errors', ['error'])
                ->groupBy('user_id')->get()
            : collect();
        $userModel = config('academic-audit.user_model');
        return $this->view('admin.settings', [
            'group' => $group,
            'fields' => SettingsRegistry::group($group),
            'settings' => $settings,
            'usage' => $usage,
            'usageUsers' => $usage->isEmpty() ? collect() : $userModel::whereIn('id', $usage->pluck('user_id'))->pluck('name', 'id'),
        ]);
    }

    public function update(Request $request, string $group, SettingsService $settings)
    {
        $this->iqac();
        abort_unless(array_key_exists($group, SettingsRegistry::GROUPS), 404);
        $rules = [];
        foreach (SettingsRegistry::group($group) as $f) {
            $name = str_replace('.', '__', $f['key']);
            $rules[$name] = match ($f['type']) {
                'bool' => 'nullable|boolean',
                'number' => 'nullable|numeric|min:0|max:100000',
                'email' => 'nullable|email|max:200',
                'select' => 'nullable|in:'.implode(',', array_keys($f['options'])),
                'textarea' => 'nullable|string|max:3000',
                default => 'nullable|string|max:500',
            };
        }
        $data = $request->validate($rules);
        foreach (SettingsRegistry::group($group) as $f) {
            $name = str_replace('.', '__', $f['key']);
            if ($f['secret']) {
                if ($request->boolean("{$name}__clear")) {
                    $settings->set($f['key'], '', auth()->id());
                } elseif (filled($data[$name] ?? null)) {
                    $settings->set($f['key'], $data[$name], auth()->id());
                }
                continue; // blank = keep saved secret
            }
            $value = $f['type'] === 'bool' ? $request->boolean($name) : ($data[$name] ?? $f['default']);
            $settings->set($f['key'], $value, auth()->id());
        }
        return redirect()->route('audit.admin.settings', ['tab' => $group])->with('status', SettingsRegistry::GROUPS[$group][0].' settings saved.');
    }

    public function testMail(Notifier $notifier, SettingsService $settings)
    {
        $this->iqac();
        if (! $settings->get('mail.enabled')) {
            return back()->withErrors(['mail' => 'Turn on "Send emails from the portal" and save first.']);
        }
        try {
            $settings->applyToConfig();
            app('mail.manager')->forgetMailers();
            $notifier->test(auth()->user());
        } catch (\Throwable $e) {
            return back()->withErrors(['mail' => 'Test mail failed: '.$e->getMessage()]);
        }
        return back()->with('status', 'Test mail sent to '.auth()->user()->email.'.');
    }

    public function testAi(DocumentExtractor $extractor)
    {
        $this->iqac();
        try {
            $msg = $extractor->ping();
        } catch (ExtractionException $e) {
            return back()->withErrors(['ai' => 'AI connection failed: '.$e->getMessage()]);
        }
        return back()->with('status', $msg);
    }
}
