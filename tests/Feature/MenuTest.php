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
            ->assertSee('>Proyectos</span>', escape: false)
            ->assertSee('>Mi perfil</span>', escape: false);
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

            foreach (['>Proyectos</span>', '>Equipo</span>', '>Mi perfil</span>'] as $boton) {
                $this->assertStringContainsString($boton, $html);
            }
        }
    }

    /**
     * En pantallas angostas la barra lateral se abre sobre el contenido con
     * un boton que es CSS puro: el mismo HTML sirve para las dos pantallas y
     * la media query decide cual de las dos formas se usa.
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
        $this->assertStringContainsString('>Proyectos</span>', $html);
        $this->assertStringContainsString('>Mi perfil</span>', $html);
    }

    /**
     * El rol vive al pie de la barra lateral, debajo del nombre, como pide la
     * Marca Madre. En la topbar va solo el avatar.
     */
    public function test_el_rol_va_al_pie_de_la_barra_y_tambien_en_el_perfil(): void
    {
        $miembro = User::factory()->create();

        $this->actingAs($miembro)
            ->get(route('projects.index'))
            ->assertSee('class="rol"', escape: false)
            ->assertSee('Socio');

        $this->actingAs($miembro)
            ->get(route('profile.edit'))
            ->assertSee('Socio');
    }
}
