<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitacora del panel: quien hizo que y cuando.
 *
 * No reemplaza a project_updates, que es el historial visible de cada proyecto
 * y parte de la herramienta. Esto es para mirar el panel entero: intentos de
 * ingreso fallidos, quien borro algo, que se movio en la semana.
 *
 * Solo se escribe, nunca se edita: por eso no hay updated_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // Null cuando no se sabe quien fue: un ingreso con un correo que
            // no existe no tiene usuario detras.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('action', 40);

            // nullOnDelete y no cascade: si se borra el proyecto, el registro
            // de que alguien lo borro tiene que sobrevivir. Por eso tambien se
            // guarda el nombre aparte, congelado.
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();

            // Nombre del proyecto, o el correo que se intento en un ingreso.
            $table->string('subject', 160)->nullable();

            // El detalle del cambio: "Nuevo → Inicio", "Alta → Baja".
            $table->string('detail', 255)->nullable();

            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['created_at', 'id']);
            $table->index('action');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
