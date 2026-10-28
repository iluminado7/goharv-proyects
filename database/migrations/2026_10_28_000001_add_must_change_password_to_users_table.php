<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las cuentas nuevas nacen con una clave que eligio otro y que viajo por
 * correo. Esta marca obliga a cambiarla antes de hacer nada en el panel, asi
 * la del correo sirve una sola vez.
 *
 * Las cuentas que ya existen quedan en false: nadie les mando una clave por
 * mail, no hay nada que forzar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
