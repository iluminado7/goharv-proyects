<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El panel se mira por ngrok y algun dia detras de un HTTPS propio. Si Laravel
 * no lee las cabeceras del proxy, arma los enlaces con http://localhost y el
 * CSS y los formularios apuntan a la maquina de quien mira, no al panel.
 */
class ProxyUrlTest extends TestCase
{
    use RefreshDatabase;

    private const PROXY = [
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host'  => 'ejemplo.ngrok-free.dev',
    ];

    public function test_el_css_y_el_formulario_salen_con_el_host_del_proxy(): void
    {
        $html = $this->get('/login', self::PROXY)->assertOk()->getContent();

        $this->assertStringContainsString('https://ejemplo.ngrok-free.dev/css/goharv.css', $html);
        $this->assertStringContainsString('action="https://ejemplo.ngrok-free.dev/login"', $html);
        $this->assertStringNotContainsString('http://localhost', $html);
    }

    public function test_los_redirects_tambien_respetan_el_proxy(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/', self::PROXY)
            ->assertRedirect('https://ejemplo.ngrok-free.dev/proyectos');
    }

    public function test_sin_proxy_las_urls_quedan_como_siempre(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('http://localhost/css/goharv.css', escape: false);
    }
}
