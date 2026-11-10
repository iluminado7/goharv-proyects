<?php

namespace Tests\Feature;

use App\Enums\ActivityAction;
use App\Mail\CacciatoreMessageReceived;
use App\Models\Activity;
use App\Models\CacciatoreMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** El formulario de contacto de cacciatoreabogados.com.ar (POST /api/cacciatore). */
class CacciatoreApiTest extends TestCase
{
    use RefreshDatabase;

    private const TURNSTILE = 'challenges.cloudflare.com/*';

    /**
     * Lo que contesta Cloudflare en cada test. Va en una propiedad porque con
     * Http::fake() gana el primer stub que coincide: uno nuevo en el test no
     * reemplazaria al de setUp().
     */
    private \Closure $cloudflare;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.turnstile.secret'     => 'secreto-de-prueba',
            'services.cacciatore.mail_to'   => 'contacto@cacciatoreabogados.com.ar, socios@cacciatoreabogados.com.ar',
            'services.cacciatore.mail_from' => 'no-reply@cacciatoreabogados.com.ar',
            // 'array' cuenta como "sin correo" (Avisos::hayCorreo): para probar
            // el envio hace falta un transporte de verdad, que Mail::fake() reemplaza.
            'mail.default'                  => 'resend',
        ]);

        $this->cloudflare = fn () => Http::response(['success' => true]);
        Http::fake([self::TURNSTILE => fn () => ($this->cloudflare)()]);
        Mail::fake();
    }

    /** Lo que manda el formulario del sitio (js/main.js). */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name'      => 'Ana',
            'last_name' => 'Pérez',
            'company'   => 'Empresa SA',
            'email'     => 'ana@empresa.com.ar',
            'phone'     => '+5491145678901',
            'country'   => 'Argentina',
            'message'   => "Necesitamos asesoramiento societario.\nGracias.",
            'legal'     => true,
            'privacy'   => true,
            'page'      => '/',
            'locale'    => 'es',
            'turnstile' => 'token-del-widget',
        ], $overrides);
    }

    private function enviar(array $overrides = [])
    {
        return $this->postJson(route('api.cacciatore.store'), $this->payload($overrides));
    }

    public function test_guarda_la_consulta(): void
    {
        $this->enviar()->assertCreated()->assertExactJson(['ok' => true]);

        $consulta = CacciatoreMessage::sole();

        $this->assertSame('Ana Pérez', $consulta->deQuien());
        $this->assertSame('Empresa SA', $consulta->company);
        $this->assertSame('es', $consulta->locale);
        $this->assertNotNull($consulta->consented_at);
        $this->assertNotNull($consulta->ip);
    }

    public function test_un_idioma_desconocido_se_guarda_sin_idioma(): void
    {
        $this->enviar(['locale' => 'fr'])->assertCreated();

        $this->assertNull(CacciatoreMessage::sole()->locale);
    }

    public function test_manda_el_mail_al_estudio_con_reply_to_del_visitante(): void
    {
        $this->enviar()->assertCreated();

        Mail::assertSent(CacciatoreMessageReceived::class, function (CacciatoreMessageReceived $mail) {
            return $mail->hasTo('contacto@cacciatoreabogados.com.ar')
                && $mail->hasTo('socios@cacciatoreabogados.com.ar')
                // Sale del estudio, no "como" el visitante: eso lo manda a spam.
                && $mail->hasFrom('no-reply@cacciatoreabogados.com.ar')
                && $mail->hasReplyTo('ana@empresa.com.ar')
                && $mail->hasSubject('Nueva consulta web: Ana Pérez (Empresa SA)');
        });
    }

    public function test_el_mail_escapa_lo_que_escribio_el_visitante(): void
    {
        // render() arma el mailer por defecto; sin clave, el de Resend no arranca.
        config(['mail.default' => 'array']);

        $consulta = new CacciatoreMessage($this->payload(['message' => '<script>alert(1)</script>']));
        $consulta->created_at = now();

        $html = (new CacciatoreMessageReceived($consulta))->render();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_verifica_el_token_con_cloudflare(): void
    {
        $this->enviar()->assertCreated();

        Http::assertSent(fn (HttpRequest $r) => $r['secret'] === 'secreto-de-prueba'
            && $r['response'] === 'token-del-widget');
    }

    public function test_un_token_rechazado_no_guarda_ni_manda_nada(): void
    {
        $this->cloudflare = fn () => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']]);

        $this->enviar()->assertUnprocessable()->assertJsonValidationErrors('turnstile');

        $this->assertSame(0, CacciatoreMessage::count());
        Mail::assertNothingSent();
    }

    public function test_sin_token_no_consulta_a_cloudflare(): void
    {
        $this->enviar(['turnstile' => ''])->assertUnprocessable()->assertJsonValidationErrors('turnstile');

        Http::assertNothingSent();
    }

    /** Falla cerrado: una clave olvidada no puede dejar pasar a todos los bots. */
    public function test_sin_clave_secreta_rechaza_el_envio(): void
    {
        config(['services.turnstile.secret' => null]);

        $this->enviar()->assertUnprocessable();

        $this->assertSame(0, CacciatoreMessage::count());
    }

    public function test_si_cloudflare_no_responde_rechaza_el_envio(): void
    {
        $this->cloudflare = fn () => Http::failedConnection();

        $this->enviar()->assertUnprocessable();

        $this->assertSame(0, CacciatoreMessage::count());
    }

    /** Si Resend falla, la consulta ya esta guardada y el visitante ve el "¡Gracias!". */
    public function test_si_el_mail_falla_la_consulta_queda_guardada(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Resend caido'));

        $this->enviar()->assertCreated();

        $this->assertSame(1, CacciatoreMessage::count());
    }

    /** Con MAIL_MAILER=log el mail entero, con datos personales, quedaria en el log. */
    public function test_sin_correo_configurado_no_manda_el_mail(): void
    {
        config(['mail.default' => 'log']);

        $this->enviar()->assertCreated();

        $this->assertSame(1, CacciatoreMessage::count());
        Mail::assertNothingSent();
    }

    public function test_sin_destinatario_guarda_igual(): void
    {
        config(['services.cacciatore.mail_to' => null]);

        $this->enviar()->assertCreated();

        $this->assertSame(1, CacciatoreMessage::count());
        Mail::assertNothingSent();
    }

    public function test_acepta_el_telefono_con_espacios_y_guiones(): void
    {
        $this->enviar(['phone' => '+54 9 11 4567-8901'])->assertCreated();

        $this->assertSame('+5491145678901', CacciatoreMessage::sole()->phone);
    }

    public function test_rechaza_datos_invalidos_y_no_guarda_nada(): void
    {
        $this->enviar([
            'last_name' => '',
            'company'   => '',
            'email'     => 'no-es-un-mail',
            'phone'     => '1234',
            'message'   => str_repeat('a', 501),
            'legal'     => false,
            'privacy'   => false,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['last_name', 'company', 'email', 'phone', 'message', 'legal', 'privacy']);

        $this->assertSame(0, CacciatoreMessage::count());
        Http::assertNothingSent();
    }

    /** Un salto de linea en el nombre o la empresa terminaria en las cabeceras del mail. */
    public function test_rechaza_saltos_de_linea_en_los_campos_que_van_al_asunto(): void
    {
        $this->enviar([
            'name'    => "Ana\r\nBcc: victima@example.com",
            'company' => "Empresa\nSA",
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'company']);
    }

    /**
     * El sitio no manda `Accept: application/json`. Sin la regla de
     * bootstrap/app.php, el error seria una redireccion que el fetch sigue
     * hasta un 200, y el sitio mostraria "¡Gracias!" sin haber guardado nada.
     */
    public function test_el_error_es_json_aunque_no_se_pida_json(): void
    {
        $this->post(route('api.cacciatore.store'), $this->payload(['email' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_el_campo_trampa_responde_ok_sin_guardar_ni_mandar(): void
    {
        $this->enviar(['website' => 'http://spam.example'])->assertCreated();

        $this->assertSame(0, CacciatoreMessage::count());
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_frena_los_envios_repetidos(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->enviar()->assertCreated();
        }

        $this->enviar()->assertTooManyRequests();
        $this->assertSame(5, CacciatoreMessage::count());
    }

    public function test_cors_solo_para_los_origenes_configurados(): void
    {
        config(['cors.allowed_origins' => ['https://cacciatoreabogados.com.ar']]);

        $preflight = fn (string $origin) => $this->call('OPTIONS', route('api.cacciatore.store'), server: [
            'HTTP_ORIGIN'                         => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD'  => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
        ]);

        $preflight('https://cacciatoreabogados.com.ar')
            ->assertHeader('Access-Control-Allow-Origin', 'https://cacciatoreabogados.com.ar');

        $this->assertNotSame(
            'https://otro-sitio.example',
            $preflight('https://otro-sitio.example')->headers->get('Access-Control-Allow-Origin')
        );
    }

    /** Sin config/cors.php, Laravel acepta cualquier origen en api/*. */
    public function test_sin_origenes_configurados_no_acepta_ninguno(): void
    {
        config(['cors.allowed_origins' => []]);

        $respuesta = $this->call('OPTIONS', route('api.cacciatore.store'), server: [
            'HTTP_ORIGIN'                        => 'https://otro-sitio.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);

        $this->assertNotContains($respuesta->headers->get('Access-Control-Allow-Origin'), ['*', 'https://otro-sitio.example']);
    }

    public function test_avisa_al_equipo_interno_y_no_al_externo(): void
    {
        $socio   = User::factory()->create();
        $externo = User::factory()->external()->create();
        $deBaja  = User::factory()->inactive()->create();

        $this->enviar()->assertCreated();

        $aviso = $socio->notifications()->sole();
        $this->assertSame('consulta_web', $aviso->data['tipo']);
        $this->assertSame('Ana Pérez', $aviso->data['proyecto']);
        $this->assertSame('Empresa SA', $aviso->data['detalle']);

        $this->assertSame(0, $externo->notifications()->count());
        $this->assertSame(0, $deBaja->notifications()->count());
    }

    public function test_queda_en_la_bitacora(): void
    {
        $this->enviar()->assertCreated();

        $registro = Activity::sole();
        $this->assertSame(ActivityAction::ConsultaRecibida->value, $registro->action instanceof ActivityAction ? $registro->action->value : $registro->action);
        $this->assertSame('Ana Pérez', $registro->subject);
        $this->assertSame('Empresa SA', $registro->detail);
    }
}
