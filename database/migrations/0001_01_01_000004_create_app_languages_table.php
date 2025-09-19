<?php

use Aslnbxrz\SimpleTranslation\Services\AppLanguageService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (AppLanguageService::usingDatabase()) {
            Schema::create('app_languages', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->index();
                $table->string('icon')->nullable();
                $table->boolean('is_active')->default(false)->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (AppLanguageService::usingDatabase()) {
            Schema::dropIfExists('app_languages');
        }
    }
};
