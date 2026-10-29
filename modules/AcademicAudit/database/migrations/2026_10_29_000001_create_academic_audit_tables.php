<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * All tables are prefixed aa_ so the module can be merged into the IQAC database.
 * MSSQL notes: no enum columns, no ON DELETE RESTRICT (unsupported by SQL Server, use NO ACTION) and no multiple cascade paths (only one cascading FK
 * chain per table, the rest restrict / set null), json stored as nvarchar(max).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aa_departments', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('short_name', 30)->nullable();
            $t->boolean('is_academic')->default(true);
            $t->text('about')->nullable();
            $t->text('vision')->nullable();
            $t->text('mission')->nullable();
            $t->timestamps();
        });

        Schema::create('aa_department_aliases', function (Blueprint $t) {
            $t->id();
            $t->foreignId('department_id')->constrained('aa_departments')->cascadeOnDelete();
            $t->string('alias')->unique();
        });

        Schema::create('aa_faculty', function (Blueprint $t) {
            $t->id();
            $t->foreignId('department_id')->nullable()->constrained('aa_departments')->nullOnDelete();
            $t->unsignedBigInteger('user_id')->nullable()->index();
            $t->string('employee_code', 50)->nullable();
            $t->string('salutation', 20)->nullable();
            $t->string('name');
            $t->string('designation')->nullable();
            $t->string('qualification')->nullable();
            $t->string('nature', 50)->nullable();
            $t->date('date_of_joining')->nullable();
            $t->date('date_of_retirement')->nullable();
            $t->date('date_of_birth')->nullable();
            $t->string('email')->nullable()->index();
            $t->string('mobile', 20)->nullable();
            $t->boolean('is_regular')->default(true);
            $t->boolean('is_active')->default(true);
            $t->string('source', 20)->default('manual');
            $t->timestamps();
        });

        Schema::create('aa_user_roles', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->index();
            $t->string('role', 20); // iqac | hod | faculty | auditor
            $t->foreignId('department_id')->nullable()->constrained('aa_departments')->nullOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'role', 'department_id']);
        });

        Schema::create('aa_cycles', function (Blueprint $t) {
            $t->id();
            $t->string('academic_year', 20)->unique(); // e.g. 2025-26
            $t->date('opens_on')->nullable();
            $t->date('closes_on')->nullable();
            $t->string('status', 20)->default('open'); // open | closed
            $t->string('awarding_rule', 20)->default('average'); // average | min | max
            $t->timestamps();
        });

        Schema::create('aa_parameters', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cycle_id')->constrained('aa_cycles')->cascadeOnDelete();
            $t->string('code', 10);
            $t->string('section', 1);
            $t->string('title', 500);
            $t->decimal('max_marks', 6, 2);
            $t->string('scoring', 10)->default('slab'); // slab | yes_no | manual
            $t->text('notes')->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->unique(['cycle_id', 'code']);
        });

        Schema::create('aa_parameter_slabs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parameter_id')->constrained('aa_parameters')->cascadeOnDelete();
            $t->string('op', 3)->default('gt'); // gt | gte
            $t->decimal('threshold', 14, 4);
            $t->decimal('marks', 6, 2);
            $t->unsignedInteger('sort_order')->default(0);
        });

        Schema::create('aa_audits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cycle_id')->constrained('aa_cycles')->cascadeOnDelete();
            $t->foreignId('department_id')->constrained('aa_departments')->noActionOnDelete();
            $t->string('status', 20)->default('draft');
            $t->unsignedBigInteger('hod_user_id')->nullable();
            $t->unsignedBigInteger('auditor1_user_id')->nullable();
            $t->unsignedBigInteger('auditor2_user_id')->nullable();
            $t->json('basic_info')->nullable();
            $t->json('scalars')->nullable();
            $t->text('iqac_remark')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->timestamp('auditor1_done_at')->nullable();
            $t->timestamp('auditor2_done_at')->nullable();
            $t->timestamp('locked_at')->nullable();
            $t->timestamps();
            $t->unique(['cycle_id', 'department_id']);
        });

        Schema::create('aa_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('audit_id')->constrained('aa_audits')->cascadeOnDelete();
            $t->string('parameter_code', 10);
            $t->json('data');
            $t->foreignId('faculty_id')->nullable()->constrained('aa_faculty')->nullOnDelete();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->string('status', 20)->default('approved'); // pending | approved | rejected
            $t->string('source', 20)->default('manual'); // manual | faculty | import | iqac
            $t->string('source_ref', 100)->nullable();
            $t->string('proof_path')->nullable();
            $t->string('remark', 500)->nullable();
            $t->timestamps();
            $t->index(['audit_id', 'parameter_code']);
        });

        Schema::create('aa_scores', function (Blueprint $t) {
            $t->id();
            $t->foreignId('audit_id')->constrained('aa_audits')->cascadeOnDelete();
            $t->string('parameter_code', 10);
            $t->decimal('metric_value', 16, 4)->nullable();
            $t->decimal('claimed_marks', 6, 2)->nullable();
            $t->string('iqac_status', 20)->nullable(); // verified | returned
            $t->string('iqac_remark', 1000)->nullable();
            $t->decimal('auditor1_marks', 6, 2)->nullable();
            $t->string('auditor1_remark', 1000)->nullable();
            $t->decimal('auditor2_marks', 6, 2)->nullable();
            $t->string('auditor2_remark', 1000)->nullable();
            $t->decimal('awarded_marks', 6, 2)->nullable();
            $t->timestamps();
            $t->unique(['audit_id', 'parameter_code']);
        });

        Schema::create('aa_attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('audit_id')->constrained('aa_audits')->cascadeOnDelete();
            $t->string('slot', 20); // parameter code (A.d) or annexure (ANX-I)
            $t->string('original_name');
            $t->string('path');
            $t->unsignedBigInteger('size')->default(0);
            $t->unsignedBigInteger('uploaded_by')->nullable();
            $t->timestamps();
            $t->index(['audit_id', 'slot']);
        });

        Schema::create('aa_audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('audit_id')->constrained('aa_audits')->cascadeOnDelete();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('action', 50);
            $t->string('detail', 1000)->nullable();
            $t->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['aa_audit_logs', 'aa_attachments', 'aa_scores', 'aa_entries', 'aa_audits', 'aa_parameter_slabs',
            'aa_parameters', 'aa_cycles', 'aa_user_roles', 'aa_faculty', 'aa_department_aliases', 'aa_departments'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
