<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Para que un telefono ofrezca instalar la app hacen falta tres cosas: el
 * manifest enlazado, iconos de 192 y 512, y un service worker registrado.
 * Si falta una, el navegador no dice nada: simplemente no aparece la opcion.
 */
class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_panel_enlaza_el_manifest_y_los_iconos(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get(route('projects.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('rel="manifest"', $html);
        $this->assertStringContainsString('manifest.webmanifest', $html);
        $this->assertStringContainsString('rel="apple-touch-icon"', $html);
        $this->assertStringContainsString('name="theme-color"', $html);
        $this->assertStringContainsString('serviceWorker', $html);
    }

    public function test_el_login_tambien_es_instalable(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('manifest.webmanifest', escape: false)
            ->assertSee('serviceWorker', escape: false);
    }

    public function test_el_color_de_la_barra_sigue_al_tema_elegido(): void
    {
        $this->get('/login')->assertSee('content="#000000"', escape: false);

        $this->withCookie(\App\Http\Controllers\ThemeController::COOKIE, 'claro')
            ->get('/login')
            ->assertSee('content="#FAF9F7"', escape: false);
    }

    public function test_estan_los_archivos_que_pide_el_navegador(): void
    {
        $publico = public_path();

        foreach ([
            'manifest.webmanifest',
            'sw.js',
            'offline.html',
            'icons/icon-192.png',
            'icons/icon-512.png',
            'icons/icon-maskable-512.png',
            'icons/apple-touch-icon.png',
        ] as $archivo) {
            $this->assertFileExists($publico.'/'.$archivo, "Falta {$archivo}");
        }
    }

    public function test_el_manifest_declara_lo_minimo_para_instalar(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);

        $this->assertSame('standalone', $manifest['display']);
        $this->assertNotEmpty($manifest['name']);
        $this->assertNotEmpty($manifest['short_name']);

        $tamanos = array_column($manifest['icons'], 'sizes');

        $this->assertContains('192x192', $tamanos);
        $this->assertContains('512x512', $tamanos);
        $this->assertContains('maskable', array_column($manifest['icons'], 'purpose'));

        // Rutas relativas: el panel puede colgar de una subcarpeta.
        foreach ($manifest['icons'] as $icono) {
            $this->assertStringStartsNotWith('/', $icono['src']);
        }

        $this->assertStringStartsNotWith('/', $manifest['start_url']);
    }

    public function test_el_service_worker_no_guarda_paginas_con_datos(): void
    {
        $sw = file_get_contents(public_path('sw.js'));

        // Las navegaciones van a la red y solo caen a offline.html: si esto
        // cambia, el telefono guardaria los proyectos del equipo en disco.
        $this->assertStringContainsString("pedido.mode === 'navigate'", $sw);
        $this->assertStringContainsString('offline.html', $sw);
        $this->assertStringContainsString("pedido.method !== 'GET'", $sw);
    }
}
