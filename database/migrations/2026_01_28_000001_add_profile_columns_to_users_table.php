<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('job_title')->nullable()->after('email');
            $table->string('phone')->nullable()->after('job_title');
            $table->string('photo_path')->nullable()->after('phone');
            $table->json('skills')->nullable()->after('photo_path');
            $table->boolean('is_active')->default(true)->after('skills');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['job_title', 'phone', 'photo_path', 'skills', 'is_active']);
        });
    }
};
