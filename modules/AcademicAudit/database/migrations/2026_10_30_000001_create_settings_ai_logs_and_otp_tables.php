<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aa_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key', 100)->unique();
            $t->text('value')->nullable();
            $t->boolean('is_encrypted')->default(false);
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamp('updated_at')->nullable();
        });

        Schema::create('aa_ai_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable()->index();
            $t->unsignedBigInteger('audit_id')->nullable();
            $t->string('parameter_code', 10)->nullable();
            $t->string('feature', 30);
            $t->string('model', 60)->nullable();
            $t->unsignedInteger('input_tokens')->default(0);
            $t->unsignedInteger('output_tokens')->default(0);
            $t->string('status', 20);
            $t->string('error', 1000)->nullable();
            $t->timestamp('created_at')->nullable()->index();
        });

        Schema::create('aa_password_otps', function (Blueprint $t) {
            $t->id();
            $t->string('email')->index();
            $t->string('code_hash');
            $t->timestamp('expires_at');
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->timestamp('used_at')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aa_password_otps');
        Schema::dropIfExists('aa_ai_logs');
        Schema::dropIfExists('aa_settings');
    }
};
