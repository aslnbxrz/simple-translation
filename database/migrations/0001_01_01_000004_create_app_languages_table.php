<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('app_languages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->index();
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_languages');
    }
};