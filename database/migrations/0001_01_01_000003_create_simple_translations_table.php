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

        Schema::create('app_texts', function (Blueprint $table) {
            $table->id();
            $table->string('scope')->nullable()->index();
            $table->text('text');
        });

        Schema::create('app_text_translations', function (Blueprint $table) {
            $table->foreignId('app_text_id')->constrained('app_texts')->cascadeOnDelete();
            $table->string('lang_code')->index();
            $table->text('text');
            $table->primary(['app_text_id', 'lang_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (AppLanguageService::usingDatabase()) {
            Schema::dropIfExists('app_languages');
        }
        Schema::dropIfExists('app_texts');
        Schema::dropIfExists('app_text_translations');
    }
};
