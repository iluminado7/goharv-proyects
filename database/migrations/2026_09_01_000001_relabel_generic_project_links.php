<?php

use App\Models\ProjectLink;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hasta ahora, un enlace cargado sin nombre se guardaba como "Enlace" a secas.
 * Dos enlaces asi en el mismo proyecto quedaban con botones identicos que
 * llevaban a lugares distintos. El nombre ahora sale de la URL, pero eso solo
 * corre al guardar: las filas viejas hay que rebautizarlas aca.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('project_links')
            ->where('label', 'Enlace')
            ->orderBy('id')
            ->each(function ($link) {
                DB::table('project_links')
                    ->where('id', $link->id)
                    ->update(['label' => ProjectLink::labelFromUrl($link->url)]);
            });
    }

    public function down(): void
    {
        // No hay forma de saber cuales eran genericos antes de la migracion, y
        // volver a poner "Enlace" en todos borraria nombres puestos a mano.
    }
};
