<?php

use Illuminate\Support\Facades\Route;
use Mmmut\AcademicAudit\Http\Controllers as C;

// Public: notice attachments are linked from the login page as well.
Route::middleware('web')
    ->prefix(config('academic-audit.route_prefix'))
    ->name('audit.')
    ->group(function () {
        Route::get('notices/{notice}/file', [C\NoticeFileController::class, 'show'])->name('notices.file');
    });

Route::middleware(config('academic-audit.middleware'))
    ->prefix(config('academic-audit.route_prefix'))
    ->name('audit.')
    ->group(function () {
        Route::get('/', [C\DashboardController::class, 'index'])->name('dashboard');
        Route::post('role', [C\RoleController::class, 'switch'])->name('role.switch');
        Route::get('ping', fn () => response()->noContent())->name('ping'); // keeps the session alive

        Route::prefix('audits/{audit}')->scopeBindings()->group(function () {
            Route::get('/', [C\AuditController::class, 'show'])->name('show');

            Route::get('basic', [C\BasicInfoController::class, 'edit'])->name('basic');
            Route::post('basic', [C\BasicInfoController::class, 'update'])->name('basic.update');
            Route::post('basic/prefill', [C\BasicInfoController::class, 'prefill'])->name('basic.prefill');

            Route::get('inputs', [C\ScalarController::class, 'edit'])->name('inputs');
            Route::post('inputs', [C\ScalarController::class, 'update'])->name('inputs.update');

            Route::get('items/{code}', [C\EntryController::class, 'index'])->name('items');
            Route::post('items/{code}', [C\EntryController::class, 'store'])->name('items.store');
            Route::post('items/{code}/prefill', [C\EntryController::class, 'prefill'])->name('items.prefill');
            Route::get('items/{code}/template', [C\EntryController::class, 'template'])->name('items.template');
            Route::post('items/{code}/import', [C\EntryController::class, 'import'])->name('items.import');
            Route::post('items/{code}/extract', [C\AiController::class, 'extract'])->middleware('throttle:20,1')->name('items.extract');
            Route::put('entries/{entry}', [C\EntryController::class, 'update'])->name('entries.update');
            Route::delete('entries/{entry}', [C\EntryController::class, 'destroy'])->name('entries.destroy');
            Route::post('entries/{entry}/review', [C\EntryController::class, 'review'])->name('entries.review');
            Route::get('entries/{entry}/proof', [C\EntryController::class, 'proof'])->name('entries.proof');

            Route::post('attachments', [C\AttachmentController::class, 'store'])->name('attachments.store');
            Route::get('attachments/{attachment}', [C\AttachmentController::class, 'show'])->name('attachments.show');
            Route::delete('attachments/{attachment}', [C\AttachmentController::class, 'destroy'])->name('attachments.destroy');

            Route::get('evaluation', [C\EvaluationController::class, 'show'])->name('evaluation');
            Route::post('evaluation/claims', [C\EvaluationController::class, 'claims'])->name('evaluation.claims');
            Route::post('evaluation/verify', [C\EvaluationController::class, 'verify'])->name('evaluation.verify');
            Route::post('evaluation/marks', [C\EvaluationController::class, 'marks'])->name('evaluation.marks');
            Route::post('auditors', [C\EvaluationController::class, 'auditors'])->name('auditors');

            Route::post('workflow/{action}', [C\WorkflowController::class, 'handle'])->name('workflow')
                ->whereIn('action', ['submit', 'return', 'verify', 'auditor-done', 'reopen', 'lock', 'unlock']);

            Route::get('report', [C\ReportController::class, 'show'])->name('report');
            Route::get('report.pdf', [C\ReportController::class, 'pdf'])->name('report.pdf');
            Route::get('report.xlsx', [C\ReportController::class, 'excel'])->name('report.xlsx');
        });

        Route::get('reports/university', [C\ReportController::class, 'university'])->name('reports.university');
        Route::get('reports/university.xlsx', [C\ReportController::class, 'universityExcel'])->name('reports.university.xlsx');

        Route::prefix('admin')->name('admin.')->group(function () {
            Route::get('cycles', [C\Admin\CycleController::class, 'index'])->name('cycles');
            Route::post('cycles', [C\Admin\CycleController::class, 'store'])->name('cycles.store');
            Route::put('cycles/{cycle}', [C\Admin\CycleController::class, 'update'])->name('cycles.update');

            Route::get('cycles/{cycle}/parameters', [C\Admin\ParameterController::class, 'index'])->name('parameters');
            Route::get('parameters/{parameter}', [C\Admin\ParameterController::class, 'edit'])->name('parameters.edit');
            Route::put('parameters/{parameter}', [C\Admin\ParameterController::class, 'update'])->name('parameters.update');
            Route::post('cycles/{cycle}/parameters/reset', [C\Admin\ParameterController::class, 'reset'])->name('parameters.reset');

            Route::get('users', [C\Admin\UserController::class, 'index'])->name('users');
            Route::post('users', [C\Admin\UserController::class, 'store'])->name('users.store');
            Route::get('users/import', [C\Admin\UserController::class, 'importForm'])->name('users.import');
            Route::get('users/import/template', [C\Admin\UserController::class, 'importTemplate'])->name('users.import.template');
            Route::post('users/import', [C\Admin\UserController::class, 'importPreview'])->name('users.import.preview');
            Route::post('users/import/confirm', [C\Admin\UserController::class, 'importConfirm'])->name('users.import.confirm');
            Route::post('users/import/cancel', [C\Admin\UserController::class, 'importCancel'])->name('users.import.cancel');
            Route::delete('roles/{role}', [C\Admin\UserController::class, 'destroy'])->name('roles.destroy');
            Route::post('users/{user}/reset-password', [C\Admin\UserController::class, 'resetPassword'])->name('users.reset');
            Route::post('users/{user}/send-otp', [C\Admin\UserController::class, 'sendOtp'])->name('users.otp');

            Route::get('notices', [C\Admin\NoticeController::class, 'index'])->name('notices');
            Route::post('notices', [C\Admin\NoticeController::class, 'store'])->name('notices.store');
            Route::put('notices/{notice}', [C\Admin\NoticeController::class, 'update'])->name('notices.update');
            Route::post('notices/{notice}/toggle', [C\Admin\NoticeController::class, 'toggle'])->name('notices.toggle');
            Route::delete('notices/{notice}', [C\Admin\NoticeController::class, 'destroy'])->name('notices.destroy');

            Route::get('settings', [C\Admin\SettingsController::class, 'index'])->name('settings');
            Route::post('settings/test-mail', [C\Admin\SettingsController::class, 'testMail'])->name('settings.test-mail');
            Route::post('settings/test-ai', [C\Admin\SettingsController::class, 'testAi'])->name('settings.test-ai');
            Route::post('settings/{group}', [C\Admin\SettingsController::class, 'update'])->name('settings.update');

            Route::get('departments', [C\Admin\DepartmentController::class, 'index'])->name('departments');
            Route::post('departments', [C\Admin\DepartmentController::class, 'store'])->name('departments.store');
            Route::post('departments/{department}/aliases', [C\Admin\DepartmentController::class, 'alias'])->name('departments.alias');

            Route::get('faculty', [C\Admin\FacultyController::class, 'index'])->name('faculty');
            Route::post('faculty', [C\Admin\FacultyController::class, 'store'])->name('faculty.store');
            Route::put('faculty/{faculty}', [C\Admin\FacultyController::class, 'update'])->name('faculty.update');
            Route::post('faculty/import', [C\Admin\FacultyController::class, 'import'])->name('faculty.import');
        });
    });
