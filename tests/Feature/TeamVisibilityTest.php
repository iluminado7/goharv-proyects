<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Saber quién está en el equipo le sirve a cualquiera: es a quién pedirle algo.
 * Administrarlo —sumar gente, cambiar permisos, dar de baja— no.
 */
class TeamVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_miembro_ve_la_lista(): void
    {
        $miembro = User::factory()->create();
        User::factory()->admin()->create(['name' => 'Ana Responsable']);

        $this->actingAs($miembro)
            ->get(route('members.index'))
            ->assertOk()
            ->assertSee('Ana Responsable')
            ->assertSee('Equipo');
    }

    public function test_un_miembro_no_ve_los_controles(): void
    {
        $miembro = User::factory()->create();
        User::factory()->admin()->create();

        $html = $this->actingAs($miembro)->get(route('members.index'))->getContent();

        $this->assertStringNotContainsString('name="role"', $html);
        $this->assertStringNotContainsString('name="is_active"', $html);
        $this->assertStringNotContainsString('Sumar al equipo', $html);
        $this->assertStringContainsString('pedíselo', $html);
    }

    public function test_el_responsable_sigue_viendo_todo(): void
    {
        $admin = User::factory()->admin()->create();

        $html = $this->actingAs($admin)->get(route('members.index'))->getContent();

        $this->assertStringContainsString('name="role"', $html);
        $this->assertStringContainsString('name="is_active"', $html);
        $this->assertStringContainsString('Sumar al equipo', $html);
    }

    /** Esconder el formulario no alcanza: las rutas tienen que seguir cerradas. */
    public function test_esconder_los_botones_no_es_la_unica_defensa(): void
    {
        $miembro = User::factory()->create();
        $otro    = User::factory()->create();

        $this->actingAs($miembro)
            ->post(route('members.store'), [
                'name' => 'Colado', 'email' => 'colado@test.com',
                'password' => 'una-clave-larga', 'role' => 'admin',
            ])
            ->assertForbidden();

        $this->actingAs($miembro)
            ->patch(route('members.update', $otro), ['role' => 'admin', 'is_active' => 1])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'colado@test.com']);
        $this->assertFalse($otro->fresh()->isAdmin());
    }

    /** A quien no puede reactivarlas, las cuentas de baja solo le ensucian la lista. */
    public function test_el_miembro_ve_solo_a_los_activos(): void
    {
        $miembro = User::factory()->create();
        User::factory()->create(['name' => 'Activo Pérez']);
        User::factory()->inactive()->create(['name' => 'Fulano DeBaja']);

        $this->actingAs($miembro)
            ->get(route('members.index'))
            ->assertSee('Activo Pérez')
            ->assertDontSee('Fulano DeBaja');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('members.index'))
            ->assertSee('Fulano DeBaja');
    }

    public function test_el_menu_muestra_equipo_para_todos(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('projects.index'))
            ->assertSee('>Equipo</a>', escape: false);
    }
}
