<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pestana_muestra_los_datos_de_quien_entro(): void
    {
        $user = User::factory()->create(['name' => 'Ana Responsable']);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Mi perfil')
            ->assertSee('Ana Responsable')
            ->assertSee('Cambiar la clave');
    }

    public function test_cada_uno_edita_su_nombre_y_su_correo(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.update'), ['name' => 'Nombre Nuevo', 'email' => 'nuevo@goharv.com'])
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Nombre Nuevo', $user->name);
        $this->assertSame('nuevo@goharv.com', $user->email);
    }

    public function test_no_se_puede_pisar_el_correo_de_otro(): void
    {
        $otro = User::factory()->create(['email' => 'ocupado@goharv.com']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.update'), ['name' => $user->name, 'email' => $otro->email])
            ->assertSessionHasErrors('email');
    }

    public function test_el_perfil_no_deja_cambiarse_el_rol(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('profile.update'), [
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => 'admin',
        ]);

        $this->assertFalse($user->fresh()->isAdmin());
    }

    public function test_se_cambia_la_clave_dando_la_actual(): void
    {
        $user = User::factory()->create(['password' => 'la-de-antes']);

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password'      => 'la-de-antes',
                'password'              => 'la-de-ahora',
                'password_confirmation' => 'la-de-ahora',
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue(Hash::check('la-de-ahora', $user->fresh()->password));
    }

    public function test_sin_la_clave_actual_no_se_cambia(): void
    {
        $user = User::factory()->create(['password' => 'la-de-antes']);

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password'      => 'cualquier-cosa',
                'password'              => 'la-de-ahora',
                'password_confirmation' => 'la-de-ahora',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('la-de-antes', $user->fresh()->password));
    }

    public function test_la_clave_nueva_tiene_que_repetirse_igual(): void
    {
        $user = User::factory()->create(['password' => 'la-de-antes']);

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password'      => 'la-de-antes',
                'password'              => 'la-de-ahora',
                'password_confirmation' => 'otra-distinta',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_el_perfil_pide_sesion(): void
    {
        $this->get(route('profile.edit'))->assertRedirect('/login');
    }
}
