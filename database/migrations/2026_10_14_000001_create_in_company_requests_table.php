<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Consultas del formulario de /academy/formacion-in-company ("Quiero diseñar un
| programa"). `team_size`, `areas` y `modality` son el texto que eligio la
| persona ("11–30", "Compliance", "Presencial"), no claves: si el sitio suma
| una opcion, el panel la guarda igual en vez de rechazar la consulta.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('in_company_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('role', 120);
            $table->string('company', 120);
            $table->string('email');
            $table->string('phone', 20);
            // Cantidad de colaboradores a capacitar (obligatoria en el sitio).
            $table->string('team_size', 40);
            // Areas de interes: casillas, puede no haber ninguna.
            $table->json('areas')->nullable();
            $table->string('modality', 60)->nullable();
            // El desafio de la empresa (opcional en el sitio).
            $table->text('message')->nullable();
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
        Schema::dropIfExists('in_company_requests');
    }
};
