<?php

namespace Mmmut\AcademicAudit\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Mmmut\AcademicAudit\Models\Audit;
use Mmmut\AcademicAudit\Support\Access;
use Mmmut\AcademicAudit\Support\Catalogue;

abstract class Controller extends BaseController
{
    protected function access(): Access
    {
        return app(Access::class);
    }

    protected function viewable(Audit $audit): Audit
    {
        abort_unless($this->access()->canView($audit), 403);
        return $audit->loadMissing('cycle', 'department');
    }

    protected function editable(Audit $audit): Audit
    {
        $this->viewable($audit);
        abort_unless($this->access()->canEdit($audit), 403, 'This audit cannot be edited in its current status.');
        return $audit;
    }

    protected function iqac(): void
    {
        abort_unless($this->access()->isIqac(), 403);
    }

    protected function definition(string $code): array
    {
        $def = Catalogue::get($code);
        abort_unless($def && $def['columns'], 404);
        return $def;
    }

    protected function view(string $name, array $data = [])
    {
        return view("audit::{$name}", $data + ['layout' => config('academic-audit.layout')]);
    }
}
