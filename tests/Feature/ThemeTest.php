<?php

namespace Tests\Feature;

use App\Http\Controllers\ThemeController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_panel_arranca_con_fondo_negro(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('data-theme="oscuro"', escape: false);
    }

    public function test_el_boton_pasa_a_fondo_claro_y_vuelve(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('projects.index'))
            ->post(route('theme.toggle'))
            ->assertRedirect(route('projects.index'))
            ->assertCookie(ThemeController::COOKIE, 'claro');

        $this->actingAs($user)
            ->withCookie(ThemeController::COOKIE, 'claro')
            ->get(route('projects.index'))
            ->assertSee('data-theme="claro"', escape: false);

        $this->actingAs($user)
            ->withCookie(ThemeController::COOKIE, 'claro')
            ->from(route('projects.index'))
            ->post(route('theme.toggle'))
            ->assertCookie(ThemeController::COOKIE, 'oscuro');
    }

    /**
     * Cambiar el fondo no deberia costar una recarga. El formulario sigue ahi
     * para cuando el JS no corre; con JS el script mueve el data-theme del
     * <html> y manda el mismo POST por atras para guardar la cookie.
     */
    public function test_se_cambia_sin_recargar_y_el_form_queda_de_respaldo(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get(route('projects.index'))
            ->getContent();

        $this->assertStringContainsString('action="'.route('theme.toggle').'"', $html);
        $this->assertStringContainsString('data-tema', $html);
        $this->assertStringContainsString('documentElement.dataset.theme', $html);
    }

    /** Los dos iconos viajan siempre: el CSS muestra el que toca. */
    public function test_los_dos_iconos_estan_en_el_html(): void
    {
        foreach (['oscuro', 'claro'] as $fondo) {
            $html = $this->actingAs(User::factory()->create())
                ->withCookie(ThemeController::COOKIE, $fondo)
                ->get(route('projects.index'))
                ->getContent();

            $this->assertStringContainsString('class="icono-sol"', $html);
            $this->assertStringContainsString('class="icono-luna"', $html);
        }
    }

    public function test_el_fondo_tambien_se_cambia_desde_el_login(): void
    {
        $this->get('/login')->assertOk()->assertSee('data-theme="oscuro"', escape: false);

        $this->from('/login')
            ->post(route('theme.toggle'))
            ->assertRedirect('/login')
            ->assertCookie(ThemeController::COOKIE, 'claro');
    }
}
