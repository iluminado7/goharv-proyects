<?php

namespace Tests\Feature;

use App\Enums\ActivityAction;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Editar los datos de una cuenta —nombre, correo, permisos y, si hace falta,
 * la clave— es solo del responsable del panel.
 */
class MemberEditTest extends TestCase
{
    use RefreshDatabase;

    // --- Quién puede -------------------------------------------------------

    public function test_el_responsable_ve_el_boton_de_editar(): void
    {
        $admin = User::factory()->admin()->create();
        $otro  = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('members.index'))
            ->assertSee(route('members.edit', $otro), escape: false);
    }

    public function test_el_socio_no_ve_el_boton(): void
    {
        $socio = User::factory()->create();
        $otro  = User::factory()->create();

        $this->actingAs($socio)
            ->get(route('members.index'))
            ->assertDontSee(route('members.edit', $otro), escape: false);
    }

    /** Esconder el botón no alcanza: la ruta tiene que estar cerrada. */
    public function test_el_socio_tampoco_entra_por_la_ruta(): void
    {
        $socio = User::factory()->create();
        $otro  = User::factory()->create();

        $this->actingAs($socio)->get(route('members.edit', $otro))->assertForbidden();

        $this->actingAs($socio)->patch(route('members.update', $otro), [
            'name' => 'Pisado', 'email' => 'pisado@test.com', 'role' => 'admin',
        ])->assertForbidden();

        $this->assertNotSame('Pisado', $otro->fresh()->name);
    }

    public function test_el_externo_tampoco(): void
    {
        $externo = User::factory()->external()->create();
        $otro    = User::factory()->create();

        $this->actingAs($externo)->get(route('members.edit', $otro))->assertForbidden();
    }

    // --- Qué se edita ------------------------------------------------------

    public function test_cambia_nombre_correo_y_permisos(): void
    {
        $admin = User::factory()->admin()->create();
        $otro  = User::factory()->create(['name' => 'Nombre Viejo', 'email' => 'viejo@test.com']);

        $this->actingAs($admin)->patch(route('members.update', $otro), [
            'name'  => 'Nombre Nuevo',
            'email' => 'nuevo@test.com',
            'role'  => 'external',
        ])->assertRedirect(route('members.index'));

        $otro->refresh();

        $this->assertSame('Nombre Nuevo', $otro->name);
        $this->assertSame('nuevo@test.com', $otro->email);
        $this->assertTrue($otro->isExternal());
    }

    public function test_no_se_repite_un_correo_que_ya_esta_en_uso(): void
    {
        $admin = User::factory()->admin()->create();
        $uno   = User::factory()->create(['email' => 'uno@test.com']);
        User::factory()->create(['email' => 'dos@test.com']);

        $this->actingAs($admin)->patch(route('members.update', $uno), [
            'name' => $uno->name, 'email' => 'dos@test.com', 'role' => 'member',
        ])->assertSessionHasErrors('email');

        $this->assertSame('uno@test.com', $uno->fresh()->email);
    }

    /** Guardar sin tocar el correo no puede chocar consigo mismo. */
    public function test_se_puede_guardar_dejando_el_mismo_correo(): void
    {
        $admin = User::factory()->admin()->create();
        $otro  = User::factory()->create(['email' => 'igual@test.com']);

        $this->actingAs($admin)->patch(route('members.update', $otro), [
            'name' => 'Otro nombre', 'email' => 'igual@test.com', 'role' => 'member',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Otro nombre', $otro->fresh()->name);
    }

    // --- La clave ----------------------------------------------------------

    public function test_el_campo_de_clave_vacio_no_la_toca(): void
    {
        $admin = User::factory()->admin()->create();
        $otro  = User::factory()->create();
        $antes = $otro->password;

        $this->actingAs($admin)->patch(route('members.update', $otro), [
            'name' => $otro->name, 'email' => $otro->email, 'role' => 'member', 'password' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame($antes, $otro->fresh()->password);
    }

    public function test_se_le_puede_restablecer_la_clave(): void
    {
        $admin = User::factory()->admin()->create();
        $otro  = User::factory()->create();

        $this->actingAs($admin)->patch(route('members.update', $otro), [
            'name'     => $otro->name,
            'email'    => $otro->email,
            'role'     => 'member',
            'password' => 'una-clave-larga-y-rara',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('una-clave-larga-y-rara', $otro->fresh()->password));
    }

    public function test_una_clave_corta_se_rechaza(): void
    {
        $admin = User::factory()->admin()->create();
        $otro  = User::factory()->create();
        $antes = $otro->password;

        $this->actingAs($admin)->patch(route('members.update', $otro), [
            'name' => $otro->name, 'email' => $otro->email, 'role' => 'member', 'password' => 'corta',
        ])->assertSessionHasErrors('password');

        $this->assertSame($antes, $otro->fresh()->password);
    }

    public function test_la_clave_no_se_muestra_en_el_formulario(): void
    {
        $admin = User::factory()->admin()->create();
        $otro  = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('members.edit', $otro))
            ->assertOk()
            ->assertSee('name="password" type="password"', escape: false)
            ->assertDontSee('name="password" type="text"', escape: false);
    }

    // --- Las reglas que ya existían siguen valiendo ------------------------

    public function test_un_responsable_no_se_quita_los_permisos_a_si_mismo(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create();

        $this->actingAs($admin)->patch(route('members.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'member',
        ])->assertSessionHasErrors('role');

        $this->assertTrue($admin->fresh()->isAdmin());
    }

    // --- Bitácora ----------------------------------------------------------

    public function test_la_edicion_dice_que_se_toco(): void
    {
        $admin = User::factory()->admin()->create();
        $otro  = User::factory()->create(['name' => 'Antes', 'email' => 'antes@test.com']);

        $this->actingAs($admin)->patch(route('members.update', $otro), [
            'name'     => 'Despues',
            'email'    => 'antes@test.com',
            'role'     => 'external',
            'password' => 'una-clave-larga-y-rara',
        ]);

        $log = Activity::where('action', ActivityAction::CuentaEditada->value)->firstOrFail();

        $this->assertSame($admin->id, $log->user_id);
        $this->assertStringContainsString('nombre', $log->detail);
        $this->assertStringContainsString('Profesional externo', $log->detail);
        $this->assertStringContainsString('clave restablecida', $log->detail);
        $this->assertStringNotContainsString('correo', $log->detail);
    }

    public function test_guardar_sin_cambiar_nada_no_ensucia_la_bitacora(): void
    {
        $admin = User::factory()->admin()->create();
        $otro  = User::factory()->create();

        $this->actingAs($admin)->patch(route('members.update', $otro), [
            'name' => $otro->name, 'email' => $otro->email, 'role' => $otro->role->value,
        ]);

        $this->assertSame(0, Activity::where('action', ActivityAction::CuentaEditada->value)->count());
    }
}
