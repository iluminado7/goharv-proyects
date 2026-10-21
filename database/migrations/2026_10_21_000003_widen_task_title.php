<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 160 caracteres quedaban cortos: una tarea bien escrita ("mandar la propuesta
 * revisada con los cambios que pidio el cliente sobre el alcance") se pasa.
 *
 * Se sube a 500 en lugar de sacar el limite del todo: un titulo sin tope
 * convierte el listado en un muro, y lo que hace falta para textos largos es
 * un campo de detalle aparte, no un titulo infinito.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('title', 500)->change();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('title', 160)->change();
        });
    }
};
