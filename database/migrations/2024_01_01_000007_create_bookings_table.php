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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('workplace_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('status_id')->constrained()->onDelete('restrict');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');

            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->integer('duration_minutes'); // суммарная длительность услуг
            $table->decimal('total_price', 10, 2)->default(0);

            $table->text('comment')->nullable();
            $table->boolean('client_attended')->nullable(); // был ли клиент
            $table->timestamp('attended_at')->nullable();
            $table->foreignId('attended_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            // Индексы для быстрого поиска
            $table->index(['tenant_id', 'start_time', 'end_time']);
            $table->index(['tenant_id', 'workplace_id', 'start_time']);
            $table->index(['tenant_id', 'employee_id', 'start_time']);
            $table->index(['tenant_id', 'status_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
