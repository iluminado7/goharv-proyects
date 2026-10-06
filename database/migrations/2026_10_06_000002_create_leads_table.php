<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Pedidos de "Avisame" de las paginas de unidad: gente que quiere saber cuando
| abre un programa que todavia esta en desarrollo.
| `program` es la etiqueta interna del programa (ej. "workshop-consorcios") y
| `source`, la unidad desde la que se pidio (ej. "academy").
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email');
            $table->string('phone', 20);
            // Casilla opcional: quiere recibir novedades de la unidad por mail.
            $table->boolean('newsletter')->default(false);
            $table->string('program', 80);
            $table->string('source', 40);
            $table->string('page')->nullable();
            $table->timestamp('consented_at');
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['program', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
