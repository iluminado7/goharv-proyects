<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Assets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Entre el cache del navegador, el service worker de la PWA y el CDN, un
 * cambio de estilo puede tardar días en verse. La URL lleva la fecha del
 * archivo para que cada versión sea una dirección distinta.
 */
class AssetVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_css_sale_con_su_version(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('css/goharv.css?v='.filemtime(public_path('css/goharv.css')), escape: false);
    }

    /**
     * El cache guarda la fecha, no la URL: si guardara la URL, en un worker
     * persistente la primera visita le fijaría su host y su esquema a todas.
     */
    public function test_la_version_no_congela_el_host_ni_el_esquema(): void
    {
        $this->get('/login');

        $html = $this->get('/login', ['X-Forwarded-Proto' => 'https'])->getContent();

        $this->assertStringContainsString('https://localhost/css/goharv.css?v=', $html);
        $this->assertStringNotContainsString('http://localhost/css/goharv.css', $html);
    }

    public function test_un_archivo_inexistente_no_rompe(): void
    {
        $this->assertSame(asset('css/no-existe.css'), Assets::versioned('css/no-existe.css'));
    }

    public function test_el_recortador_solo_esta_donde_se_sube_la_foto(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('recorte-caja', escape: false)
            ->assertSee('Encuadrá tu foto');

        // No tiene por qué cargarse en el resto del panel.
        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertDontSee('recorte-caja', escape: false);
    }

    /** Sin JavaScript el formulario tiene que seguir subiendo la foto igual. */
    public function test_el_formulario_funciona_sin_el_recortador(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', escape: false)
            ->assertSee('name="avatar" type="file"', escape: false);
    }
}
