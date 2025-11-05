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
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('code')->nullable(); // confirmed, pending, cancelled_by_client, cancelled_by_admin
            $table->string('color', 7)->default('#3B82F6'); // HEX цвет для календаря
            $table->boolean('is_default')->default(false);
            $table->boolean('is_system')->default(false); // системные статусы нельзя удалить
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statuses');
    }
};
