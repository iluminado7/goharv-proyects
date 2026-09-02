<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El panel corre detras de un proxy que termina el HTTPS. De las cabeceras que
 * manda ese proxy se confia en el esquema, el puerto y la IP de origen, pero
 * NO en el host: X-Forwarded-Host la puede poner cualquiera, y sirve para que
 * el panel genere enlaces hacia un dominio ajeno.
 */
class ProxyUrlTest extends TestCase
{
    use RefreshDatabase;

    private const PROXY = ['X-Forwarded-Proto' => 'https'];

    private const PROXY_CON_HOST_FALSO = [
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host'  => 'sitio-del-atacante.test',
    ];

    public function test_el_esquema_del_proxy_se_respeta(): void
    {
        // Sin esto el navegador bloquea el CSS por contenido mixto y el
        // formulario de login no llega a enviarse.
        $html = $this->get('/login', self::PROXY)->assertOk()->getContent();

        $this->assertStringContainsString('https://localhost/css/goharv.css', $html);
        $this->assertStringContainsString('action="https://localhost/login"', $html);
        $this->assertStringNotContainsString('http://localhost', $html);
    }

    public function test_un_host_falseado_no_se_cuela_en_los_enlaces(): void
    {
        $html = $this->get('/login', self::PROXY_CON_HOST_FALSO)->assertOk()->getContent();

        $this->assertStringNotContainsString('sitio-del-atacante.test', $html);
        $this->assertStringContainsString('action="https://localhost/login"', $html);
    }

    public function test_un_host_falseado_tampoco_se_cuela_en_los_redirects(): void
    {
        // Este es el caso que importa cuando exista recuperacion de clave: el
        // enlace del correo se arma con el host de la request.
        $destino = $this->actingAs(User::factory()->create())
            ->get('/', self::PROXY_CON_HOST_FALSO)
            ->headers->get('Location');

        $this->assertStringNotContainsString('sitio-del-atacante.test', $destino);
        $this->assertSame('https://localhost/proyectos', $destino);
    }

    public function test_sin_proxy_las_urls_quedan_como_siempre(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('http://localhost/css/goharv.css', escape: false);
    }
}
