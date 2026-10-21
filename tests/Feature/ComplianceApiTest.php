<?php

namespace Tests\Feature;

use App\Models\ComplianceRequest;
use App\Models\ConsultingRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Quiero fortalecer mi empresa" (/compliance): el mismo formulario que
 * "Analizar mi empresa" (ver ConsultingApiTest), guardado aparte.
 */
class ComplianceApiTest extends TestCase
{
    use RefreshDatabase;

    /** Lo que manda hoy el formulario del sitio con JS (scripts/consulting-form.ts). */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'program'    => 'compliance-fortalecer-empresa',
            'source'     => 'compliance',
            'page'       => '/compliance',
            'challenge'  => 'canal-de-denuncias',
            'goal'       => 'Implementar un canal de denuncias.',
            'name'       => 'Marta Gómez',
            'role'       => 'Compliance Officer',
            'company'    => 'Gómez SA',
            'email'      => 'marta@gomez.com.ar',
            'phone'      => '1145671234',
            'consent'    => true,
            'newsletter' => true,
            'locale'     => 'en',
        ], $overrides);
    }

    public function test_guarda_la_consulta_en_su_propia_tabla(): void
    {
        $this->postJson(route('api.compliance.store'), $this->payload())
            ->assertCreated()
            ->assertExactJson(['ok' => true]);

        $r = ComplianceRequest::sole();

        $this->assertSame('canal-de-denuncias', $r->challenge);
        $this->assertSame('Implementar un canal de denuncias.', $r->goal);
        $this->assertSame('Gómez SA', $r->company);
        $this->assertTrue($r->newsletter);
        $this->assertSame('en', $r->locale);
        $this->assertSame('/compliance', $r->page);
        $this->assertSame(0, ConsultingRequest::count());
    }

    public function test_rechaza_datos_invalidos_y_no_guarda_nada(): void
    {
        $this->postJson(route('api.compliance.store'), $this->payload([
            'goal'    => '',
            'email'   => 'no-es-un-mail',
            'consent' => false,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['goal', 'email', 'consent']);

        $this->assertSame(0, ComplianceRequest::count());
    }

    public function test_el_campo_trampa_responde_ok_sin_guardar(): void
    {
        $this->postJson(route('api.compliance.store'), $this->payload(['website' => 'http://spam.example']))
            ->assertCreated();

        $this->assertSame(0, ComplianceRequest::count());
    }

    public function test_envio_sin_js_guarda_y_vuelve_a_compliance(): void
    {
        config(['cors.allowed_origins' => ['https://goharv.com.ar']]);

        $data = $this->payload(['consent' => 'on']);
        unset($data['locale'], $data['newsletter']);

        $this->post(route('api.compliance.store'), $data, ['Referer' => 'https://goharv.com.ar/en/compliance'])
            ->assertStatus(303)
            ->assertRedirect('https://goharv.com.ar/en/compliance?consulta=enviada#hablemos');

        $this->assertFalse(ComplianceRequest::sole()->newsletter);
    }

    public function test_tiene_su_seccion_en_consultas_web_con_el_desafio(): void
    {
        $this->postJson(route('api.compliance.store'), $this->payload(['name' => 'Marta Compliance']));
        ConsultingRequest::create([
            'challenge' => 'costos', 'goal' => 'Bajar costos', 'name' => 'Pablo Consulting',
            'role' => 'Dueño', 'company' => 'C SA', 'email' => 'c@t.test', 'phone' => '11223344',
            'consented_at' => now(),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('inquiries.index', ['form' => 'fortalecer-empresa']))
            ->assertOk()
            ->assertSee('<h1 class="page" id="inq-title">Fortalecer mi empresa</h1>', escape: false)
            ->assertSee('Marta Compliance')
            ->assertSee('Gómez SA · Compliance Officer')
            ->assertSee('Canal de Denuncias')
            ->assertSee('Implementar un canal de denuncias.')
            ->assertDontSee('Pablo Consulting');
    }

    public function test_filtra_por_desafio(): void
    {
        $this->postJson(route('api.compliance.store'), $this->payload(['name' => 'Con canal']));
        $this->postJson(route('api.compliance.store'), $this->payload(['name' => 'Con capacitacion', 'challenge' => 'capacitacion']));

        $this->actingAs(User::factory()->create())
            ->get(route('inquiries.index', ['form' => 'fortalecer-empresa', 'desafio' => 'capacitacion']))
            ->assertOk()
            ->assertSee('Con capacitacion')
            ->assertDontSee('Con canal');
    }

    public function test_avisa_al_equipo_con_el_nombre_del_formulario(): void
    {
        $user = User::factory()->create();

        $this->postJson(route('api.compliance.store'), $this->payload());

        $this->assertSame('Quiero fortalecer mi empresa', $user->fresh()->notifications->sole()->data['proyecto']);
    }
}
