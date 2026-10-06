<?php

namespace Tests\Feature;

use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadApiTest extends TestCase
{
    use RefreshDatabase;

    /** Lo que manda hoy el formulario "Avisame" del sitio (scripts/notify-form.ts). */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name'       => 'Juan Gómez',
            'email'      => 'juan@consorcio.com.ar',
            'phone'      => '2615551234',
            'consent'    => true,
            'newsletter' => true,
            'program'    => 'workshop-consorcios',
            'source'     => 'academy',
            'page'       => '/academy',
        ], $overrides);
    }

    public function test_guarda_el_pedido(): void
    {
        $this->postJson(route('api.leads.store'), $this->payload())
            ->assertCreated()
            ->assertExactJson(['ok' => true]);

        $lead = Lead::sole();

        $this->assertSame('workshop-consorcios', $lead->program);
        $this->assertSame('academy', $lead->source);
        $this->assertTrue($lead->newsletter);
        $this->assertNotNull($lead->consented_at);
    }

    public function test_sin_la_casilla_de_novedades_queda_en_falso(): void
    {
        $this->postJson(route('api.leads.store'), $this->payload(['newsletter' => false]))
            ->assertCreated();

        $this->assertFalse(Lead::sole()->newsletter);
    }

    public function test_rechaza_datos_invalidos_y_no_guarda_nada(): void
    {
        $this->postJson(route('api.leads.store'), $this->payload([
            'phone'   => 'abc',
            'consent' => false,
            'program' => 'Programa con espacios',
            'source'  => '',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone', 'consent', 'program', 'source']);

        $this->assertSame(0, Lead::count());
    }

    public function test_el_campo_trampa_responde_ok_sin_guardar(): void
    {
        $this->postJson(route('api.leads.store'), $this->payload(['website' => 'x']))
            ->assertCreated();

        $this->assertSame(0, Lead::count());
    }
}
