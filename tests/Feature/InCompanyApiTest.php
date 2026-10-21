<?php

namespace Tests\Feature;

use App\Models\InCompanyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InCompanyApiTest extends TestCase
{
    use RefreshDatabase;

    /** Lo que manda hoy el formulario del sitio (scripts/in-company-form.ts). */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name'       => 'Laura Díaz',
            'role'       => 'Gerenta de RRHH',
            'company'    => 'Industrias del Sur SA',
            'email'      => 'laura@industrias.com.ar',
            'phone'      => '1145678901',
            'teamSize'   => '31–100',
            'areas'      => ['Compliance', 'Gestión comercial'],
            'modality'   => 'Presencial',
            'message'    => 'Queremos capacitar a los mandos medios.',
            'consent'    => true,
            'newsletter' => true,
            'program'    => 'academy-in-company',
            'source'     => 'academy',
            'page'       => '/academy/formacion-in-company',
            'locale'     => 'es',
        ], $overrides);
    }

    public function test_guarda_la_consulta_con_todos_sus_datos(): void
    {
        $this->postJson(route('api.in-company.store'), $this->payload())
            ->assertCreated()
            ->assertExactJson(['ok' => true]);

        $r = InCompanyRequest::sole();

        $this->assertSame('Gerenta de RRHH', $r->role);
        $this->assertSame('Industrias del Sur SA', $r->company);
        $this->assertSame('31–100', $r->team_size);
        $this->assertSame(['Compliance', 'Gestión comercial'], $r->areas);
        $this->assertSame('Presencial', $r->modality);
        $this->assertSame('Queremos capacitar a los mandos medios.', $r->message);
        $this->assertTrue($r->newsletter);
        $this->assertSame('es', $r->locale);
        $this->assertNotNull($r->consented_at);
        $this->assertNotNull($r->ip);
    }

    public function test_areas_modalidad_y_desafio_son_opcionales(): void
    {
        $this->postJson(route('api.in-company.store'), $this->payload([
            'areas'      => [],
            'modality'   => '',
            'message'    => '',
            'newsletter' => false,
        ]))->assertCreated();

        $r = InCompanyRequest::sole();
        $this->assertNull($r->areas);
        $this->assertNull($r->modality);
        $this->assertNull($r->message);
        $this->assertFalse($r->newsletter);
    }

    /** Una opcion nueva en el sitio no puede hacer que se pierdan consultas. */
    public function test_acepta_opciones_que_el_panel_no_conoce(): void
    {
        $this->postJson(route('api.in-company.store'), $this->payload([
            'teamSize' => 'Más de 500',
            'areas'    => ['Un área nueva'],
            'modality' => 'Híbrida',
        ]))->assertCreated();
    }

    public function test_un_idioma_desconocido_se_guarda_sin_idioma(): void
    {
        $this->postJson(route('api.in-company.store'), $this->payload(['locale' => 'fr']))->assertCreated();

        $this->assertNull(InCompanyRequest::sole()->locale);
    }

    public function test_rechaza_datos_invalidos_y_no_guarda_nada(): void
    {
        $this->postJson(route('api.in-company.store'), $this->payload([
            'role'     => '',
            'company'  => '',
            'email'    => 'no-es-un-mail',
            'phone'    => '11-4567',
            'teamSize' => '',
            'message'  => str_repeat('a', 501),
            'consent'  => false,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role', 'company', 'email', 'phone', 'teamSize', 'message', 'consent']);

        $this->assertSame(0, InCompanyRequest::count());
    }

    public function test_el_campo_trampa_responde_ok_sin_guardar(): void
    {
        $this->postJson(route('api.in-company.store'), $this->payload(['website' => 'http://spam.example']))
            ->assertCreated();

        $this->assertSame(0, InCompanyRequest::count());
    }

    public function test_frena_los_envios_repetidos(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('api.in-company.store'), $this->payload())->assertCreated();
        }

        $this->postJson(route('api.in-company.store'), $this->payload())->assertTooManyRequests();
        $this->assertSame(5, InCompanyRequest::count());
    }
}
