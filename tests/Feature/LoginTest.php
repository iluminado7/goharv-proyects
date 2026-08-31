<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_miembro_activo_entra_al_tablero(): void
    {
        $user = User::factory()->create(['password' => 'clave-larga']);

        $this->post('/login', ['email' => $user->email, 'password' => 'clave-larga'])
            ->assertRedirect(route('projects.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_la_clave_equivocada_no_deja_entrar(): void
    {
        $user = User::factory()->create(['password' => 'clave-larga']);

        $this->post('/login', ['email' => $user->email, 'password' => 'otra-cosa'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_una_cuenta_dada_de_baja_no_entra(): void
    {
        $user = User::factory()->inactive()->create(['password' => 'clave-larga']);

        $this->post('/login', ['email' => $user->email, 'password' => 'clave-larga'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_corta_a_los_cinco_intentos_fallidos(): void
    {
        RateLimiter::clear('login:frenado@goharv.com|127.0.0.1');

        User::factory()->create(['email' => 'frenado@goharv.com', 'password' => 'clave-larga']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'frenado@goharv.com', 'password' => 'mal']);
        }

        // El sexto intento no llega ni a chequear la clave, aunque sea la correcta.
        $this->post('/login', ['email' => 'frenado@goharv.com', 'password' => 'clave-larga'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_el_tablero_pide_sesion(): void
    {
        $this->get('/proyectos')->assertRedirect('/login');
    }
}
