<?php

namespace Database\Seeders;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@goharv.com'],
            [
                'name'     => 'Responsable GoHarv',
                'password' => 'cambiar-esta-clave',
                'role'     => 'admin',
            ]
        );

        if (app()->environment('local') && Project::count() === 0) {
            $demo = Project::create([
                'name'        => 'Panel de proyectos',
                'description' => 'Este mismo tablero. Sirve como ejemplo, borralo cuando cargues los reales.',
                'status'      => ProjectStatus::EnDesarrollo,
                'priority'    => ProjectPriority::Alta,
                'owner_id'    => $admin->id,
            ]);

            $demo->syncLinks([
                ['label' => 'Principal', 'url' => 'https://goharv.com'],
            ]);
        }
    }
}
