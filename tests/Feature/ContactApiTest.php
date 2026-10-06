<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactApiTest extends TestCase
{
    use RefreshDatabase;

    /** Lo que manda hoy el formulario del sitio (scripts/contact-form.ts). */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name'    => 'Ana Pérez',
            'email'   => 'ana@empresa.com.ar',
            'phone'   => '1145678901',
            'company' => 'Empresa SA',
            'country' => 'Argentina',
            'unit'    => 'Business',
            'message' => 'Quiero franquiciar mi negocio.',
            'consent' => true,
            'page'    => '/contacto',
        ], $overrides);
    }

    public function test_guarda_la_consulta(): void
    {
        $this->postJson(route('api.contact.store'), $this->payload())
            ->assertCreated()
            ->assertExactJson(['ok' => true]);

        $message = ContactMessage::sole();

        $this->assertSame('Ana Pérez', $message->name);
        $this->assertSame('Business', $message->unit);
        $this->assertSame('/contacto', $message->page);
        $this->assertNotNull($message->consented_at);
        $this->assertNotNull($message->ip);
    }

    public function test_los_campos_opcionales_pueden_venir_vacios(): void
    {
        $this->postJson(route('api.contact.store'), $this->payload([
            'company' => '',
            'unit'    => '',
        ]))->assertCreated();

        $message = ContactMessage::sole();
        $this->assertNull($message->company);
        $this->assertNull($message->unit);
    }

    /** Una opcion nueva en el sitio no puede hacer que se pierdan consultas. */
    public function test_acepta_una_unidad_o_un_pais_que_el_panel_no_conoce(): void
    {
        $this->postJson(route('api.contact.store'), $this->payload([
            'unit'    => 'Una unidad nueva',
            'country' => 'Uruguay',
        ]))->assertCreated();
    }

    public function test_rechaza_datos_invalidos_y_no_guarda_nada(): void
    {
        $this->postJson(route('api.contact.store'), $this->payload([
            'email'   => 'no-es-un-mail',
            'phone'   => '11-4567',
            'message' => str_repeat('a', 501),
            'consent' => false,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'phone', 'message', 'consent']);

        $this->assertSame(0, ContactMessage::count());
    }

    /**
     * El sitio no manda `Accept: application/json`. Sin la regla de
     * bootstrap/app.php, el error seria una redireccion que el fetch sigue
     * hasta un 200, y el sitio mostraria "¡Gracias!" sin haber guardado nada.
     */
    public function test_el_error_es_json_aunque_no_se_pida_json(): void
    {
        $this->post(route('api.contact.store'), $this->payload(['email' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_el_campo_trampa_responde_ok_sin_guardar(): void
    {
        $this->postJson(route('api.contact.store'), $this->payload(['website' => 'http://spam.example']))
            ->assertCreated();

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_frena_los_envios_repetidos(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('api.contact.store'), $this->payload())->assertCreated();
        }

        $this->postJson(route('api.contact.store'), $this->payload())->assertTooManyRequests();
        $this->assertSame(5, ContactMessage::count());
    }

    public function test_cors_solo_para_los_origenes_configurados(): void
    {
        config(['cors.allowed_origins' => ['https://goharv.com.ar']]);

        $preflight = fn (string $origin) => $this->call('OPTIONS', route('api.contact.store'), server: [
            'HTTP_ORIGIN'                        => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
        ]);

        $preflight('https://goharv.com.ar')
            ->assertHeader('Access-Control-Allow-Origin', 'https://goharv.com.ar');

        // Con un solo origen, la cabecera lo nombra siempre a el: el navegador de
        // otro sitio ve que no es el suyo y no deja pasar el envio.
        $ajeno = $preflight('https://otro-sitio.example');
        $this->assertNotSame(
            'https://otro-sitio.example',
            $ajeno->headers->get('Access-Control-Allow-Origin')
        );
    }
}
