<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El seeder no crea cuentas.
 *
 * Antes sembraba un admin con una contraseña escrita en el repositorio y sin
 * guarda de entorno: cualquiera que leyera el código tenía acceso de
 * responsable, y bastaba un `db:seed` contra producción para dejar esa cuenta
 * viva. El primer responsable se crea con `php artisan goharv:responsable`,
 * que pide la clave y no la guarda en ningún lado.
 */
class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_crea_ninguna_cuenta(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, User::count());
    }

    /** Ni siquiera la que existía antes, por si alguien la busca de memoria. */
    public function test_no_queda_la_cuenta_que_sembraba_antes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseMissing('users', ['email' => 'admin@goharv.com']);
    }

    public function test_no_hay_contrasenas_escritas_en_el_seeder(): void
    {
        $fuente = file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        $this->assertStringNotContainsString("'password'", $fuente);
        $this->assertStringNotContainsString('Hash::make', $fuente);
    }

    /** El proyecto de ejemplo sigue, pero solo en local. */
    public function test_siembra_el_proyecto_de_ejemplo_en_local(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Project::count());
    }

    public function test_fuera_de_local_no_siembra_nada(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        // Directo y no con seed(): el comando db:seed pide confirmación por
        // consola fuera de local, y eso es del framework, no del seeder.
        $this->app->make(DatabaseSeeder::class)->run();

        $this->assertSame(0, Project::count());
        $this->assertSame(0, User::count());
    }

    /** Sin usuarios todavía, el proyecto de ejemplo queda sin responsable. */
    public function test_el_proyecto_de_ejemplo_no_inventa_un_duenio(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        $this->seed(DatabaseSeeder::class);

        $this->assertNull(Project::first()->owner_id);
    }

    /** Si ya hay un responsable, el proyecto de ejemplo queda a su nombre. */
    public function test_si_hay_responsable_el_ejemplo_es_suyo(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        $admin = User::factory()->admin()->create();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($admin->id, Project::first()->owner_id);
    }
}
