<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aa_notices', function (Blueprint $t) {
            $t->id();
            $t->string('title', 500);
            $t->string('link_url', 1000)->nullable();
            $t->string('attachment_path')->nullable();
            $t->string('attachment_name')->nullable();
            $t->string('show_on', 10)->default('both'); // login | dashboard | both
            $t->date('starts_on')->nullable();
            $t->date('ends_on')->nullable();
            $t->boolean('is_active')->default(true);
            $t->boolean('is_new')->default(false);
            $t->unsignedInteger('sort_order')->default(0);
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aa_notices');
    }
};
