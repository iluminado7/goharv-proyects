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

    public function test_el_fondo_tambien_se_cambia_desde_el_login(): void
    {
        $this->get('/login')->assertOk()->assertSee('data-theme="oscuro"', escape: false);

        $this->from('/login')
            ->post(route('theme.toggle'))
            ->assertRedirect('/login')
            ->assertCookie(ThemeController::COOKIE, 'claro');
    }
}
