<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('sso_sub', 191)->nullable()->index();
        });

        // Q-Sphere login session id ("sid") → local user, for back-channel logout tokens that carry only a sid.
        Schema::create('sso_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('sid', 191)->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sso_sessions');
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['sso_sub']);
            $table->dropColumn('sso_sub');
        });
    }
};
