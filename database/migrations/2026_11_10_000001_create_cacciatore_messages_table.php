<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Consultas del formulario de contacto de cacciatoreabogados.com.ar, el sitio
| del estudio (estatico, en AWS, sin backend propio). Llegan por la misma API
| que los formularios de goharv.com.ar, pero van a su propia tabla: es otro
| sitio, con otros campos (nombre y apellido por separado, sin unidad de
| negocio) y se atiende aparte en Consultas web.
|
| `country` es texto libre que escribe la persona, no una clave: el sitio no
| tiene lista de paises.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cacciatore_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('last_name', 120);
            // Obligatoria en el sitio: el estudio atiende empresas.
            $table->string('company', 120);
            $table->string('email');
            $table->string('phone', 20);
            $table->string('country', 60);
            // El sitio corta en 500, pero text y no string: la validacion de
            // la API es la que manda, no el largo de la columna.
            $table->text('message');
            // Desde que pagina del sitio se envio (ej. "/" o "/team.html").
            $table->string('page')->nullable();
            // Idioma del sitio (es, en, pt): tambien es trilingue.
            $table->string('locale', 2)->nullable();
            // Cuando acepto el aviso legal y la politica de privacidad. El
            // sitio pide las dos casillas; sin ambas la API no guarda nada.
            $table->timestamp('consented_at');
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cacciatore_messages');
    }
};
