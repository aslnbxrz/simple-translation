<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
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
        Schema::dropIfExists('app_texts');
        Schema::dropIfExists('app_text_translations');
    }
};
