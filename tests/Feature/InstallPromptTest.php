<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El ofrecimiento de instalar el panel como app, una vez por dispositivo.
 */
class InstallPromptTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_panel_ofrece_instalarse(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('projects.index'))
            ->assertSee('data-instalar', escape: false)
            ->assertSee('¿Instalás el panel?');
    }

    /**
     * El evento llega apenas el navegador decide que el sitio es instalable.
     * Si el script carga después, ya se perdió: por eso va antes del
     * encabezado y no al pie como el resto.
     */
    public function test_el_oyente_se_registra_antes_del_contenido(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get(route('projects.index'))
            ->getContent();

        $oyente     = strpos($html, 'beforeinstallprompt');
        $encabezado = strpos($html, '<header class="topbar"');

        $this->assertNotFalse($oyente);
        $this->assertLessThan($encabezado, $oyente, 'Se registra tarde y el evento se pierde.');
    }

    /** iPhone no tiene beforeinstallprompt: ahí van las instrucciones. */
    public function test_contempla_el_iphone_con_instrucciones(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('projects.index'))
            ->assertSee('Agregar a pantalla de inicio');
    }

    /**
     * Instalar y notificaciones podrían querer aparecer juntos. Dos carteles
     * encimados al entrar es la forma más segura de que se cierren los dos
     * sin leer.
     */
    public function test_los_dos_carteles_se_turnan(): void
    {
        config(['webpush.vapid.public_key' => 'clave-publica']);

        $html = $this->actingAs(User::factory()->create())
            ->get(route('projects.index'))
            ->getContent();

        $this->assertSame(2, substr_count($html, 'ghDialogo.tomar()'));
        $this->assertStringContainsString('window.ghDialogo = {', $html);
    }

    /** La pantalla de clave obligatoria es una puerta: ahí no se ofrece nada. */
    public function test_no_se_ofrece_antes_de_cambiar_la_clave(): void
    {
        $this->actingAs(User::factory()->create(['must_change_password' => true]))
            ->get(route('password.change'))
            ->assertOk()
            ->assertDontSee('data-instalar', escape: false);
    }

    public function test_el_manifest_alcanza_para_instalar(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);

        $this->assertSame('standalone', $manifest['display']);
        $this->assertNotEmpty($manifest['name']);

        // Chrome exige 192 y 512 para considerar un sitio instalable.
        $medidas = array_column($manifest['icons'], 'sizes');

        $this->assertContains('192x192', $medidas);
        $this->assertContains('512x512', $medidas);

        foreach ($manifest['icons'] as $icono) {
            $this->assertFileExists(public_path($icono['src']), $icono['src'].' no existe');
        }
    }
}
