<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Quien entra con una clave que le mandaron por correo no hace nada hasta
 * cambiarla. Así la clave que quedó escrita en esa casilla sirve una vez sola.
 */
class FirstPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function reciénCreado(): User
    {
        return User::factory()->create(['must_change_password' => true]);
    }

    // --- El panel queda cerrado --------------------------------------------

    public function test_todo_el_panel_lleva_a_la_pantalla_de_clave(): void
    {
        $nuevo = $this->reciénCreado();

        foreach (['projects.index', 'members.index', 'profile.edit'] as $ruta) {
            $this->actingAs($nuevo)
                ->get(route($ruta))
                ->assertRedirect(route('password.change'));
        }
    }

    /** No alcanza con cerrar las pantallas: las acciones también. */
    public function test_tampoco_puede_hacer_cosas_por_atras(): void
    {
        $nuevo = $this->reciénCreado();

        $this->actingAs($nuevo)
            ->post(route('projects.store'), [
                'name' => 'Colado', 'status' => 'nuevo', 'priority' => 'media',
            ])
            ->assertRedirect(route('password.change'));

        $this->assertSame(0, \App\Models\Project::count());
    }

    public function test_puede_llegar_a_la_pantalla_y_salir(): void
    {
        $nuevo = $this->reciénCreado();

        $this->actingAs($nuevo)->get(route('password.change'))->assertOk();
        $this->actingAs($nuevo)->post(route('logout'))->assertRedirect();
    }

    /** Si no tiene nada que cambiar, la pantalla no tiene sentido. */
    public function test_quien_ya_la_cambio_no_entra_a_esa_pantalla(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('password.change'))
            ->assertRedirect(route('projects.index'));
    }

    public function test_a_los_demas_no_les_cambia_nada(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('projects.index'))
            ->assertOk();
    }

    // --- Cambiarla ---------------------------------------------------------

    public function test_cambiarla_abre_el_panel(): void
    {
        $nuevo = User::factory()->create([
            'password'             => 'la-del-correo-12345',
            'must_change_password' => true,
        ]);

        $this->actingAs($nuevo)->post(route('password.change.store'), [
            'current_password'      => 'la-del-correo-12345',
            'password'              => 'la-mia-propia-67890',
            'password_confirmation' => 'la-mia-propia-67890',
        ])->assertRedirect(route('projects.index'));

        $nuevo->refresh();

        $this->assertFalse($nuevo->must_change_password);
        $this->assertTrue(Hash::check('la-mia-propia-67890', $nuevo->password));

        $this->actingAs($nuevo)->get(route('projects.index'))->assertOk();
    }

    /** Dejar la misma deja la clave del correo viva: es justo lo que se evita. */
    public function test_no_puede_poner_la_misma_que_le_mandaron(): void
    {
        $nuevo = User::factory()->create([
            'password'             => 'la-del-correo-12345',
            'must_change_password' => true,
        ]);

        $this->actingAs($nuevo)->post(route('password.change.store'), [
            'current_password'      => 'la-del-correo-12345',
            'password'              => 'la-del-correo-12345',
            'password_confirmation' => 'la-del-correo-12345',
        ])->assertSessionHasErrors('password');

        $this->assertTrue($nuevo->fresh()->must_change_password);
    }

    public function test_la_clave_vieja_tiene_que_coincidir(): void
    {
        $nuevo = User::factory()->create([
            'password'             => 'la-del-correo-12345',
            'must_change_password' => true,
        ]);

        $this->actingAs($nuevo)->post(route('password.change.store'), [
            'current_password'      => 'cualquier-otra-cosa',
            'password'              => 'la-mia-propia-67890',
            'password_confirmation' => 'la-mia-propia-67890',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue($nuevo->fresh()->must_change_password);
    }

    // --- Quién nace con la marca -------------------------------------------

    public function test_una_cuenta_nueva_nace_obligada_a_cambiarla(): void
    {
        Notification::fake();

        $this->actingAs(User::factory()->admin()->create())->post(route('members.store'), [
            'name' => 'Estudio Contable', 'email' => 'estudio@ejemplo.com',
            'password' => 'una-clave-larga-y-rara', 'role' => 'external',
        ]);

        $this->assertTrue(User::where('email', 'estudio@ejemplo.com')->first()->must_change_password);
    }

    /** Mismo caso: la eligió otro y viajó por fuera del panel. */
    public function test_si_un_responsable_le_resetea_la_clave_vuelve_a_pedirsela(): void
    {
        $admin = User::factory()->admin()->create();
        $otro  = User::factory()->create();

        $this->actingAs($admin)->patch(route('members.update', $otro), [
            'name' => $otro->name, 'email' => $otro->email, 'role' => 'member',
            'password' => 'la-que-le-puso-el-jefe',
        ]);

        $this->assertTrue($otro->fresh()->must_change_password);
    }

    public function test_editar_sin_tocar_la_clave_no_lo_encierra(): void
    {
        $admin = User::factory()->admin()->create();
        $otro  = User::factory()->create();

        $this->actingAs($admin)->patch(route('members.update', $otro), [
            'name' => 'Nombre nuevo', 'email' => $otro->email, 'role' => 'member',
        ]);

        $this->assertFalse($otro->fresh()->must_change_password);
    }

    /**
     * Mi perfil no es una segunda salida: el middleware manda todo a la
     * pantalla de clave, así hay un solo camino y no dos que puedan
     * desincronizarse. ProfileController limpia la marca igual, por si una
     * ruta futura vuelve a dejar pasar ese formulario.
     */
    public function test_mi_perfil_no_es_una_segunda_salida(): void
    {
        $nuevo = $this->reciénCreado();

        $this->actingAs($nuevo)
            ->put(route('profile.password'), [
                'current_password'      => 'lo-que-sea',
                'password'              => 'la-mia-propia-67890',
                'password_confirmation' => 'la-mia-propia-67890',
            ])
            ->assertRedirect(route('password.change'));

        $this->assertTrue($nuevo->fresh()->must_change_password);
    }
}
