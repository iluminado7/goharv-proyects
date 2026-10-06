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

    /**
     * Equipo esta en el menu para todos: la lista le sirve a cualquiera para
     * saber a quien pedirle algo. Lo que cambia es lo que hay adentro, y eso
     * lo cubre TeamVisibilityTest. Consultas web tambien es para todos: las
     * consultas del sitio las atiende cualquiera del equipo.
     */
    public function test_el_menu_tiene_las_cuatro_secciones_para_todos(): void
    {
        foreach ([User::factory()->create(), User::factory()->admin()->create()] as $quien) {
            $html = $this->actingAs($quien)->get(route('projects.index'))->getContent();

            foreach (['>Proyectos</a>', '>Consultas web</a>', '>Equipo</a>', '>Mi perfil</a>'] as $boton) {
                $this->assertStringContainsString($boton, $html);
            }
        }
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
