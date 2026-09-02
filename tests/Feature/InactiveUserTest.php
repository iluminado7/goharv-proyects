<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Dar de baja tiene que expulsar en el acto. Antes solo impedia volver a
 * entrar: con la sesion abierta se seguia leyendo todo el tablero, y un
 * responsable del panel dado de baja podia entrar a Equipo y reactivarse solo.
 */
class InactiveUserTest extends TestCase
{
    use RefreshDatabase;

    /** Da de baja al usuario despues de que ya tiene la sesion abierta. */
    private function dadoDeBajaConSesionAbierta(bool $admin = false): User
    {
        $user = $admin
            ? User::factory()->admin()->create()
            : User::factory()->create();

        $this->actingAs($user);
        $user->update(['is_active' => false]);

        return $user->refresh();
    }

    public static function pantallas(): array
    {
        return [
            'tablero'    => ['projects.index'],
            'archivados' => ['projects.archived'],
            'perfil'     => ['profile.edit'],
            'alta'       => ['projects.create'],
        ];
    }

    #[DataProvider('pantallas')]
    public function test_no_entra_a_ninguna_pantalla(string $ruta): void
    {
        $this->dadoDeBajaConSesionAbierta();

        $this->get(route($ruta))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_tampoco_a_la_ficha_de_un_proyecto(): void
    {
        $project = Project::factory()->create(['name' => 'Proyecto del equipo']);
        $this->dadoDeBajaConSesionAbierta();

        $this->get(route('projects.show', $project))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }

    /** El caso mas grave: un admin de baja no puede volver a activarse. */
    public function test_un_responsable_de_baja_no_puede_reactivarse_solo(): void
    {
        User::factory()->admin()->create(); // Otro admin, para que la baja sea posible.
        $admin = $this->dadoDeBajaConSesionAbierta(admin: true);

        $this->get(route('members.index'))->assertRedirect(route('login'));

        $this->patch(route('members.update', $admin), ['role' => 'admin', 'is_active' => 1])
            ->assertRedirect(route('login'));

        $this->assertFalse($admin->fresh()->is_active);
    }

    public function test_la_sesion_queda_cerrada_de_verdad(): void
    {
        $user = $this->dadoDeBajaConSesionAbierta();

        $this->get(route('projects.index'));

        $this->assertGuest();

        // Y no alcanza con reintentar: sigue afuera.
        $this->get(route('projects.index'))->assertRedirect(route('login'));
    }

    public function test_al_activo_no_le_cambia_nada(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('projects.index'))
            ->assertOk();
    }

    public function test_la_pantalla_de_login_sigue_abierta_para_visitas(): void
    {
        $this->get('/login')->assertOk();
    }
}
