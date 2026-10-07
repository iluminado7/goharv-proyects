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
     * lo cubre TeamVisibilityTest.
     */
    public function test_el_menu_tiene_las_tres_secciones_para_todos(): void
    {
        foreach ([User::factory()->create(), User::factory()->admin()->create()] as $quien) {
            $html = $this->actingAs($quien)->get(route('projects.index'))->getContent();

            foreach (['>Proyectos</a>', '>Equipo</a>', '>Mi perfil</a>'] as $boton) {
                $this->assertStringContainsString($boton, $html);
            }
        }
    }

    /**
     * En el celular los cuatro botones ocupaban media pantalla. Se colapsan
     * detras de un boton que es CSS puro: el mismo HTML sirve para las dos
     * pantallas y la media query decide.
     */
    public function test_el_menu_del_celular_sale_en_el_mismo_html(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get(route('projects.index'))
            ->getContent();

        $this->assertStringContainsString('id="abrir-menu"', $html);
        $this->assertStringContainsString('class="hamburguesa"', $html);
        $this->assertStringContainsString('aria-label="Abrir el menú"', $html);

        // Los enlaces estan en el HTML siempre: el boton solo los esconde con
        // CSS en pantallas chicas, no cambia lo que se manda.
        $this->assertStringContainsString('>Proyectos</a>', $html);
        $this->assertStringContainsString('>Mi perfil</a>', $html);
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
