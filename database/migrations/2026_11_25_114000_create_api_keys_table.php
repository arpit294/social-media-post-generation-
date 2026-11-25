<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('api_key');
            $table->string('model')->default('gpt-4o-mini');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('active_api_key_id')->nullable()->constrained('api_keys')->nullOnDelete();
            $table->dropColumn(['llm_provider', 'openai_api_key', 'openai_model']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('active_api_key_id');
            $table->string('llm_provider')->default('local');
            $table->text('openai_api_key')->nullable();
            $table->string('openai_model')->default('gpt-4o-mini');
        });

        Schema::dropIfExists('api_keys');
    }
};
