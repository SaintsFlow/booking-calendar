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
        // Добавляем поля графика работы для мест работы
        Schema::table('workplaces', function (Blueprint $table) {
            $table->json('working_hours')->nullable()->after('sort_order')
                ->comment('График работы по умолчанию: {monday: {start: "09:00", end: "18:00", is_working: true}, ...}');
        });

        // Добавляем поля графика работы и отпусков для сотрудников
        Schema::table('users', function (Blueprint $table) {
            $table->json('working_hours')->nullable()->after('is_admin')
                ->comment('Персональный график работы сотрудника (переопределяет график места работы)');
            $table->json('custom_schedules')->nullable()->after('working_hours')
                ->comment('Особые графики на конкретные даты: [{date: "2025-11-01", start: "10:00", end: "16:00", is_working: true}]');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workplaces', function (Blueprint $table) {
            $table->dropColumn('working_hours');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['working_hours', 'custom_schedules']);
        });
    }
};
