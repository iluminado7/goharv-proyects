<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Consultas del formulario de contacto del sitio (home y /contacto).
| `unit` y `country` son el texto que eligio la persona ("Business",
| "No estoy seguro", "Argentina"), no claves: si el sitio suma una opcion, el
| panel la guarda igual en vez de rechazar la consulta.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email');
            $table->string('phone', 20);
            $table->string('company', 120)->nullable();
            $table->string('country', 60)->nullable();
            $table->string('unit', 60)->nullable();
            $table->text('message');
            // Desde que pagina del sitio se envio (ej. "/contacto").
            $table->string('page')->nullable();
            // Cuando acepto el aviso legal y la politica de privacidad.
            $table->timestamp('consented_at');
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
