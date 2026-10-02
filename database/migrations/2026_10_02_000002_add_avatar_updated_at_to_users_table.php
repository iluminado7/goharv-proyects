<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de tiempo de la foto, duplicada en users a proposito.
 *
 * Resuelve dos cosas de una. La URL de la foto la lleva como sufijo, asi que
 * al cambiarla la direccion cambia y ninguna capa de cache puede servir la
 * vieja. Y saber si alguien tiene foto deja de costar una consulta por cara
 * dibujada: en el historial de un proyecto eso era una consulta por linea.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('avatar_updated_at')->nullable()->after('is_active');
        });

        DB::table('users')->update([
            'avatar_updated_at' => DB::raw('(select updated_at from user_avatars where user_avatars.user_id = users.id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_updated_at');
        });
    }
};
