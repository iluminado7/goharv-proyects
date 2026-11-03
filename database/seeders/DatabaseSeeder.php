<?php

namespace Database\Seeders;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * El seeder arma el tablero de ejemplo para desarrollo. No crea cuentas.
 *
 * Antes sembraba un responsable con la contraseña escrita acá mismo, y sin
 * guarda de entorno: quien leyera el repositorio tenía la clave, y bastaba un
 * `db:seed` contra producción para dejar esa cuenta viva y usable. No hay
 * forma seria de poner una contraseña en un archivo versionado, así que no se
 * pone ninguna.
 *
 * El primer responsable se crea con `php artisan goharv:responsable`, que la
 * pide por teclado y no la guarda en ningún lado.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('El seeder solo corre en local. No se sembró nada.');

            return;
        }

        if (Project::count() > 0) {
            return;
        }

        // A nombre del primer responsable que haya. Si todavía no hay nadie,
        // el proyecto queda sin responsable: el panel lo contempla y se
        // asigna desde la pantalla de edición.
        $duenio = User::where('role', UserRole::Admin)->orderBy('id')->first();

        $demo = Project::create([
            'name'        => 'Panel de proyectos',
            'description' => 'Este mismo tablero. Sirve como ejemplo, borralo cuando cargues los reales.',
            'status'      => ProjectStatus::EnDesarrollo,
            'priority'    => ProjectPriority::Alta,
            'owner_id'    => $duenio?->id,
        ]);

        $demo->syncLinks([
            ['label' => 'Principal', 'url' => 'https://goharv.com'],
        ]);
    }
}
