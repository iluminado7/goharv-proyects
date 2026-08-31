<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_menu_lleva_a_proyectos_y_a_mi_perfil(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('>Proyectos</a>', escape: false)
            ->assertSee('>Mi perfil</a>', escape: false);
    }

    public function test_solo_el_responsable_del_panel_ve_equipo(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('projects.index'))
            ->assertDontSee('>Equipo</a>', escape: false);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('projects.index'))
            ->assertSee('>Equipo</a>', escape: false);
    }

    public function test_el_rol_ya_no_esta_en_el_header_sino_en_el_perfil(): void
    {
        $miembro = User::factory()->create();

        $this->actingAs($miembro)
            ->get(route('projects.index'))
            ->assertDontSee('class="rol"', escape: false);

        $this->actingAs($miembro)
            ->get(route('profile.edit'))
            ->assertSee('Miembro');
    }
}
