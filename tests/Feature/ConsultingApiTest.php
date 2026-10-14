<?php

namespace Tests\Feature;

use App\Models\ConsultingRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsultingApiTest extends TestCase
{
    use RefreshDatabase;

    /** Lo que manda hoy el formulario del sitio con JS (scripts/consulting-form.ts). */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'program'    => 'consulting-analizar-empresa',
            'source'     => 'consulting',
            'page'       => '/consulting',
            'challenge'  => 'ia-automatizacion',
            'goal'       => 'Automatizar la atención al cliente.',
            'name'       => 'Pablo Ruiz',
            'role'       => 'Director',
            'company'    => 'Ruiz Hnos SRL',
            'email'      => 'pablo@ruiz.com.ar',
            'phone'      => '3415551234',
            'consent'    => true,
            'newsletter' => false,
            'locale'     => 'es',
        ], $overrides);
    }

    public function test_guarda_la_consulta(): void
    {
        $this->postJson(route('api.consulting.store'), $this->payload())
            ->assertCreated()
            ->assertExactJson(['ok' => true]);

        $r = ConsultingRequest::sole();

        $this->assertSame('ia-automatizacion', $r->challenge);
        $this->assertSame('Automatizar la atención al cliente.', $r->goal);
        $this->assertSame('Director', $r->role);
        $this->assertSame('Ruiz Hnos SRL', $r->company);
        $this->assertFalse($r->newsletter);
        $this->assertSame('/consulting', $r->page);
        $this->assertNotNull($r->consented_at);
    }

    public function test_rechaza_datos_invalidos_y_no_guarda_nada(): void
    {
        $this->postJson(route('api.consulting.store'), $this->payload([
            'challenge' => 'Algo <raro>',
            'goal'      => str_repeat('a', 501),
            'company'   => '',
            'phone'     => '123',
            'consent'   => false,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['challenge', 'goal', 'company', 'phone', 'consent']);

        $this->assertSame(0, ConsultingRequest::count());
    }

    /** Un desafio nuevo en el sitio se guarda igual (el panel lo muestra pasado en limpio). */
    public function test_acepta_un_desafio_que_el_panel_no_conoce(): void
    {
        $this->postJson(route('api.consulting.store'), $this->payload(['challenge' => 'sucesion-familiar']))
            ->assertCreated();
    }

    public function test_el_campo_trampa_responde_ok_sin_guardar(): void
    {
        $this->postJson(route('api.consulting.store'), $this->payload(['website' => 'http://spam.example']))
            ->assertCreated();

        $this->assertSame(0, ConsultingRequest::count());
    }

    /**
     * Sin JavaScript el navegador manda el <form> comun: `consent=on`,
     * `newsletter=on` solo si se marco, y espera una pagina, no JSON.
     */
    public function test_envio_sin_js_guarda_y_vuelve_a_la_pagina_del_sitio(): void
    {
        config(['cors.allowed_origins' => ['https://goharv.com.ar']]);

        $data = $this->payload(['consent' => 'on', 'newsletter' => 'on']);
        unset($data['locale']);

        $this->post(route('api.consulting.store'), $data, ['Referer' => 'https://goharv.com.ar/consulting?x=1#otra'])
            ->assertStatus(303)
            ->assertRedirect('https://goharv.com.ar/consulting?consulta=enviada#hablemos');

        $r = ConsultingRequest::sole();
        $this->assertTrue($r->newsletter);
        $this->assertNotNull($r->consented_at);
    }

    /** Sin la casilla de novedades marcada, el navegador ni siquiera manda el campo. */
    public function test_envio_sin_js_sin_novedades(): void
    {
        $data = $this->payload(['consent' => 'on']);
        unset($data['newsletter']);

        $this->post(route('api.consulting.store'), $data);

        $this->assertFalse(ConsultingRequest::sole()->newsletter);
    }

    /** El panel no redirige a dominios ajenos: si el origen no es el sitio, contesta sin redirigir. */
    public function test_no_redirige_a_un_dominio_ajeno(): void
    {
        config(['cors.allowed_origins' => ['https://goharv.com.ar']]);

        $this->post(route('api.consulting.store'), $this->payload(['consent' => 'on']), ['Referer' => 'https://otro-sitio.example/x'])
            ->assertCreated();
    }
}
