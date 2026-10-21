<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Consultas del formulario "Quiero fortalecer mi empresa" de /compliance. Es el
| mismo formulario de tres pasos que "Analizar mi empresa" (desafio, que quiere
| conseguir y datos de contacto), con sus propias opciones en el paso 1:
| `challenge` es la clave que manda el sitio ("canal-de-denuncias"); el titulo
| que se ve en el panel sale de WebForms::COMPLIANCE_CHALLENGES.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_requests', function (Blueprint $table) {
            $table->id();
            $table->string('challenge', 40);
            // "¿Qué querés conseguir?"
            $table->text('goal');
            $table->string('name', 120);
            $table->string('role', 120);
            $table->string('company', 120);
            $table->string('email');
            $table->string('phone', 20);
            $table->boolean('newsletter')->default(false);
            $table->string('page')->nullable();
            $table->string('locale', 2)->nullable();
            // Cuando acepto el aviso legal y la politica de privacidad.
            $table->timestamp('consented_at');
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_requests');
    }
};
