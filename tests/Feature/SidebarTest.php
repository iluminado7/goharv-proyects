<?php

namespace Tests\Feature;

use App\Http\Controllers\SidebarController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La barra lateral se pliega con una cookie y no con un checkbox: si fuera un
 * checkbox volveria a abrirse en cada navegacion, que es justo lo que molesta.
 */
class SidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_arranca_ancha(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('projects.index'))
            ->assertDontSee('class="barra-angosta"', escape: false);
    }

    public function test_plegarla_la_deja_plegada_en_la_siguiente_pagina(): void
    {
        $quien = User::factory()->create();

        $this->actingAs($quien)
            ->post(route('sidebar.toggle'))
            ->assertRedirect()
            ->assertCookie(SidebarController::COOKIE, 'angosta');

        $this->actingAs($quien)
            ->withCookie(SidebarController::COOKIE, 'angosta')
            ->get(route('projects.index'))
            ->assertSee('class="barra-angosta"', escape: false);
    }

    public function test_volver_a_tocarla_la_abre(): void
    {
        $this->actingAs(User::factory()->create())
            ->withCookie(SidebarController::COOKIE, 'angosta')
            ->post(route('sidebar.toggle'))
            ->assertCookie(SidebarController::COOKIE, 'ancha');
    }

    /** Plegada el texto sigue en el HTML: lo esconde el CSS, no el servidor. */
    public function test_plegada_no_saca_los_nombres_del_html(): void
    {
        $this->actingAs(User::factory()->create())
            ->withCookie(SidebarController::COOKIE, 'angosta')
            ->get(route('projects.index'))
            ->assertSee('>Proyectos</span>', escape: false)
            ->assertSee('title="Proyectos"', escape: false);
    }

    /**
     * Plegar no deberia costar una recarga. El formulario sigue ahi para
     * cuando el JS no corre; con JS el script lo intercepta, mueve la clase en
     * el acto y manda el mismo POST por atras solo para guardar la cookie.
     */
    public function test_se_pliega_sin_recargar_y_el_form_queda_de_respaldo(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get(route('projects.index'))
            ->getContent();

        $this->assertStringContainsString('preventDefault', $html);
        $this->assertStringContainsString("action=\"".route('sidebar.toggle')."\"", $html);
    }

    public function test_hay_pie_de_pagina_en_todas(): void
    {
        $quien = User::factory()->admin()->create();

        foreach (['projects.index', 'members.index', 'activity.index', 'profile.edit'] as $ruta) {
            $this->actingAs($quien)->get(route($ruta))
                ->assertSee('GoHarv Project Manager · '.now()->year);
        }
    }
}
