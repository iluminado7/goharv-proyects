<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La foto va en una tabla aparte y no en una columna de users: el blob pesa
 * mas que toda la fila y no tiene sentido arrastrarlo en cada listado de
 * miembros, en cada historial o en cada carga del tablero.
 *
 * Tampoco va al disco: en Laravel Cloud el filesystem se borra en cada deploy.
 * Con cinco personas son unos 100 KB contra 5 GB de base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_avatars', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('mime', 40);
            $table->binary('image');
            $table->timestamps();
        });

        // binary() da un BLOB de 64 KB en MySQL y una foto puede pasarse.
        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE user_avatars MODIFY image MEDIUMBLOB NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_avatars');
    }
};
