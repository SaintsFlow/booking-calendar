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
        Schema::create('employee_vacations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->date('start_date')->comment('Начало отпуска');
            $table->date('end_date')->comment('Конец отпуска');
            $table->string('type')->default('vacation')->comment('vacation, sick_leave, day_off');
            $table->text('reason')->nullable()->comment('Причина/комментарий');
            $table->timestamps();

            $table->index(['employee_id', 'start_date', 'end_date']);
            $table->index(['tenant_id', 'start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_vacations');
    }
};
