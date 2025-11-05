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
        Schema::create('tenant_bitrix24_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');

            // Основные настройки
            $table->boolean('enabled')->default(false);
            $table->text('webhook_url')->nullable(); // Зашифрованный webhook URL

            // Настройки контакта
            $table->string('contact_type_id')->default('CLIENT');
            $table->string('contact_source_id')->default('WEBFORM');
            $table->string('contact_honorific')->nullable();
            $table->enum('contact_opened', ['Y', 'N'])->default('Y');

            // Настройки сделки
            $table->integer('deal_category_id')->default(0);
            $table->string('deal_stage_id')->default('NEW');
            $table->string('deal_type_id')->default('SALE');
            $table->string('deal_source_id')->default('WEBFORM');
            $table->string('deal_currency_id')->default('RUB');
            $table->enum('deal_opened', ['Y', 'N'])->default('Y');
            $table->integer('deal_probability')->default(50);

            // Лимиты
            $table->integer('max_contacts_for_deal_search')->default(10);
            $table->integer('max_duplicate_values')->default(20);

            $table->timestamps();

            // Уникальность: один набор настроек на тенанта
            $table->unique('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_bitrix24_settings');
    }
};
