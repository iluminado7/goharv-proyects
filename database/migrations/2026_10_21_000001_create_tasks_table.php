<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tareas dentro de un proyecto.
 *
 * El estado son dos: pendiente o completada. Se guarda como completed_at
 * nullable y no como un booleano: da los mismos dos estados y de paso queda
 * cuando se completo, que en este panel siempre termina haciendo falta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();

            // La tarea es parte del proyecto: si el proyecto se borra de
            // verdad, se va con el. Archivar no la toca (es softDelete).
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            $table->string('title', 160);
            $table->date('due_date')->nullable();

            // Vacio = pendiente. Con fecha = completada.
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // El listado de un proyecto separa pendientes de completadas.
            $table->index(['project_id', 'completed_at']);
            // Para "lo que vence pronto" sin recorrer todo.
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
