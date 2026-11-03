<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Quién entra a la pantalla de ARCA.
 *
 * Aunque sea de prueba, emite comprobantes: con ARCA_PRODUCTION en true son
 * fiscales y reales. Antes estaba fuera de 'auth' y la protegía una sola
 * condición, el entorno. Ahora pide sesión y rol de responsable, igual que
 * Actividad, y el entorno queda como tercera traba y no como la única.
 */
class ArcaAccessTest extends TestCase
{
    use RefreshDatabase;

    private const RUTAS = ['arca.test', 'arca.estado', 'arca.conexion'];

    public function test_sin_sesion_manda_al_login(): void
    {
        foreach (self::RUTAS as $ruta) {
            $this->get(route($ruta))->assertRedirect(route('login'));
        }

        $this->post(route('arca.test.emitir'), ['importe' => 1000])
            ->assertRedirect(route('login'));
    }

    public function test_un_socio_no_entra(): void
    {
        $socio = User::factory()->create();

        foreach (self::RUTAS as $ruta) {
            $this->actingAs($socio)->get(route($ruta))->assertForbidden();
        }

        $this->actingAs($socio)
            ->post(route('arca.test.emitir'), ['importe' => 1000])
            ->assertForbidden();
    }

    public function test_un_externo_tampoco(): void
    {
        $this->actingAs(User::factory()->external()->create())
            ->get(route('arca.test'))
            ->assertForbidden();
    }

    public function test_una_cuenta_de_baja_tampoco(): void
    {
        $this->actingAs(User::factory()->admin()->inactive()->create())
            ->get(route('arca.test'))
            ->assertRedirect(route('login'));
    }

    /** Con la clave sin cambiar, el panel entero está cerrado. */
    public function test_con_la_clave_pendiente_tampoco(): void
    {
        $this->actingAs(User::factory()->admin()->create(['must_change_password' => true]))
            ->get(route('arca.test'))
            ->assertRedirect(route('password.change'));
    }

    public function test_el_responsable_entra(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('arca.test'))
            ->assertOk()
            ->assertSee('Pruebas de ARCA');
    }

    // --- Integrada al panel ------------------------------------------------

    public function test_usa_el_layout_del_panel(): void
    {
        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('arca.test'))
            ->getContent();

        // La barra lateral y el pie: si está el layout, están estos.
        $this->assertStringContainsString('<aside class="barra"', $html);
        $this->assertStringContainsString('class="gh-footer"', $html);
    }

    public function test_el_responsable_ve_la_pestana_en_el_menu(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('projects.index'))
            ->assertSee(route('arca.test'), escape: false);
    }

    public function test_el_socio_no_ve_la_pestana(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('projects.index'))
            ->assertDontSee('arca/test', escape: false);
    }

    // --- CSRF ---------------------------------------------------------------

    /**
     * El POST tenía el CSRF desactivado para poder pegarle con curl. Con
     * sesión obligatoria eso ya no servía de nada y solo dejaba la puerta
     * abierta a que un sitio ajeno hiciera emitir una factura.
     */
    public function test_el_post_de_emitir_exige_csrf(): void
    {
        $this->assertStringNotContainsString(
            'VerifyCsrfToken',
            file_get_contents(base_path('routes/web.php')),
        );
    }

    /**
     * La traba que no depende de rutas ni de middleware. Un
     * ARCA_PRODUCTION=true copiado sin querer de un .env a otro alcanzaria
     * para emitir comprobantes fiscales reales mientras se prueba.
     */
    public function test_no_se_emite_contra_produccion_desde_otro_entorno(): void
    {
        config([
            'arca.production'   => true,
            'arca.access_token' => 'un-token-cualquiera',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ARCA_PRODUCTION esta en true fuera de produccion');

        new \App\Services\ArcaService();
    }

    public function test_en_homologacion_el_servicio_arranca(): void
    {
        config([
            'arca.production'   => false,
            'arca.access_token' => 'un-token-cualquiera',
        ]);

        $this->assertInstanceOf(\App\Services\ArcaService::class, new \App\Services\ArcaService());
    }
}
