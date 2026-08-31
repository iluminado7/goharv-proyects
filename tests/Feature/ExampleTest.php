<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_raiz_lleva_al_login_sin_sesion(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk();
    }

    /**
     * A la URL completa, no a "/proyectos" pelado: si el panel cuelga de una
     * subcarpeta (XAMPP en /goharv-panel/public) la ruta cruda se va del sitio.
     */
    public function test_la_raiz_lleva_al_tablero_con_sesion(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertRedirect(route('projects.index'));
    }

    public function test_la_raiz_no_redirige_a_una_ruta_relativa(): void
    {
        $destino = $this->actingAs(User::factory()->create())
            ->get('/')
            ->headers->get('Location');

        $this->assertStringStartsWith('http', $destino);
    }
}
