<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Avisos en el celular. Una suscripción es por dispositivo, no por persona:
 * el mismo usuario puede tener el teléfono activado y la computadora no.
 */
class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://fcm.googleapis.com/fcm/send/un-endpoint-cualquiera';

    private function datos(string $endpoint = self::ENDPOINT): array
    {
        return [
            'endpoint' => $endpoint,
            'keys'     => ['p256dh' => 'una-clave-publica', 'auth' => 'un-token'],
        ];
    }

    public function test_un_dispositivo_se_suscribe(): void
    {
        $quien = User::factory()->create();

        $this->actingAs($quien)
            ->postJson(route('push.store'), $this->datos())
            ->assertOk();

        $this->assertDatabaseHas('push_subscriptions', [
            'subscribable_id' => $quien->id,
            'endpoint'        => self::ENDPOINT,
        ]);
    }

    public function test_cada_dispositivo_es_una_suscripcion(): void
    {
        $quien = User::factory()->create();

        $this->actingAs($quien)->postJson(route('push.store'), $this->datos('https://fcm.googleapis.com/fcm/send/telefono'));
        $this->actingAs($quien)->postJson(route('push.store'), $this->datos('https://fcm.googleapis.com/fcm/send/compu'));

        $this->assertCount(2, $quien->pushSubscriptions);
    }

    /** Volver a mandar el mismo endpoint lo actualiza, no lo duplica. */
    public function test_suscribirse_dos_veces_desde_el_mismo_no_duplica(): void
    {
        $quien = User::factory()->create();

        $this->actingAs($quien)->postJson(route('push.store'), $this->datos());
        $this->actingAs($quien)->postJson(route('push.store'), $this->datos());

        $this->assertCount(1, $quien->pushSubscriptions);
    }

    public function test_se_puede_desactivar(): void
    {
        $quien = User::factory()->create();

        $this->actingAs($quien)->postJson(route('push.store'), $this->datos());

        $this->actingAs($quien)
            ->deleteJson(route('push.destroy'), ['endpoint' => self::ENDPOINT])
            ->assertOk();

        $this->assertCount(0, $quien->fresh()->pushSubscriptions);
    }

    public function test_sin_sesion_no_se_suscribe_nadie(): void
    {
        $this->postJson(route('push.store'), $this->datos())->assertUnauthorized();

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_hacen_falta_las_dos_claves(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('push.store'), ['endpoint' => self::ENDPOINT])
            ->assertJsonValidationErrors(['keys.p256dh', 'keys.auth']);
    }

    /** Una cuenta dada de baja no puede dejarse avisos enganchados. */
    public function test_una_cuenta_de_baja_no_se_suscribe(): void
    {
        $this->actingAs(User::factory()->inactive()->create())
            ->postJson(route('push.store'), $this->datos())
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    /**
     * ContentEncoding es un enum y from() tira ValueError con cualquier cosa
     * que no sea aesgcm o aes128gcm: sin validarlo, un valor raro es un 500.
     */
    public function test_un_content_encoding_invalido_se_rechaza_no_revienta(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('push.store'), $this->datos() + ['content_encoding' => 'cualquier-cosa'])
            ->assertJsonValidationErrors('content_encoding');

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_los_dos_encodings_de_verdad_se_aceptan(): void
    {
        $quien = User::factory()->create();

        foreach (['aesgcm', 'aes128gcm'] as $i => $encoding) {
            $this->actingAs($quien)
                ->postJson(route('push.store'), $this->datos('https://fcm.googleapis.com/fcm/send/'.$i)
                    + ['content_encoding' => $encoding])
                ->assertOk();
        }

        $this->assertCount(2, $quien->pushSubscriptions);
    }

    /** El endpoint lo da el navegador y es siempre una URL https. */
    public function test_un_endpoint_que_no_es_una_url_se_rechaza(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('push.store'), $this->datos('esto no es una url'))
            ->assertJsonValidationErrors('endpoint');

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_un_endpoint_sin_https_se_rechaza(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('push.store'), $this->datos('http://fcm.googleapis.com/fcm/send/abc'))
            ->assertJsonValidationErrors('endpoint');
    }

    /** Nadie puede dar de baja el dispositivo de otro. */
    public function test_no_se_puede_borrar_la_suscripcion_ajena(): void
    {
        $duenio = User::factory()->create();
        $ajeno  = User::factory()->create();

        $this->actingAs($duenio)->postJson(route('push.store'), $this->datos());

        $this->actingAs($ajeno)
            ->deleteJson(route('push.destroy'), ['endpoint' => self::ENDPOINT])
            ->assertOk();

        $this->assertCount(1, $duenio->fresh()->pushSubscriptions);
    }

    // --- El contenido del aviso -------------------------------------------

    public function test_el_aviso_lleva_el_titulo_el_proyecto_y_el_enlace(): void
    {
        $quien   = User::factory()->create();
        $project = Project::factory()->create(['name' => 'Local Rosario']);

        $mensaje = (new ProjectEvent(NotificationType::TareaAsignada, $project, null, 'Mandar el balance'))
            ->toWebPush($quien, new ProjectEvent(NotificationType::TareaAsignada, $project));

        $payload = $mensaje->toArray();

        $this->assertSame('Te asignaron una tarea', $payload['title']);
        $this->assertStringContainsString('Local Rosario', $payload['body']);
        $this->assertStringContainsString('Mandar el balance', $payload['body']);
        $this->assertSame(route('projects.show', $project), $payload['data']['url']);
    }

    /**
     * El badge de la barra de estado tiene que ser una silueta.
     *
     * Android descarta los colores del badge y se queda con el canal alfa,
     * que pinta de un color solido. Con el icono comun —opaco de punta a
     * punta— se veia un cuadrado blanco. El archivo tiene que tener zonas
     * transparentes o vuelve a pasar.
     */
    public function test_el_badge_es_una_silueta_y_no_un_cuadrado(): void
    {
        $quien   = User::factory()->create();
        $project = Project::factory()->create();

        $payload = (new ProjectEvent(NotificationType::TeAsignaron, $project))
            ->toWebPush($quien, new ProjectEvent(NotificationType::TeAsignaron, $project))
            ->toArray();

        $this->assertStringContainsString('badge-96.png', $payload['badge']);
        $this->assertStringNotContainsString('badge', $payload['icon'], 'El icono grande va a color');

        $archivo = public_path('icons/badge-96.png');
        $this->assertFileExists($archivo);

        $png  = imagecreatefrompng($archivo);
        $alfa = fn (int $x, int $y) => (imagecolorat($png, $x, $y) >> 24) & 0x7F;

        $this->assertSame(127, $alfa(2, 2), 'La esquina tiene que ser transparente');

        $opacos = 0;
        for ($y = 0; $y < 96; $y += 2) {
            for ($x = 0; $x < 96; $x += 2) {
                if ($alfa($x, $y) < 64) {
                    $opacos++;
                }
            }
        }

        $cobertura = $opacos / (48 * 48);

        $this->assertGreaterThan(0.05, $cobertura, 'No se ve nada');
        $this->assertLessThan(0.60, $cobertura, 'Es un bloque, no una letra');
    }

    /** Tres avisos del mismo proyecto se pisan en vez de apilarse. */
    public function test_los_avisos_del_mismo_proyecto_comparten_etiqueta(): void
    {
        $quien   = User::factory()->create();
        $project = Project::factory()->create();

        $uno = (new ProjectEvent(NotificationType::TeSumaron, $project))->toWebPush($quien, new ProjectEvent(NotificationType::TeSumaron, $project));
        $dos = (new ProjectEvent(NotificationType::TareaAsignada, $project))->toWebPush($quien, new ProjectEvent(NotificationType::TareaAsignada, $project));

        $this->assertSame($uno->toArray()['tag'], $dos->toArray()['tag']);
    }

    // --- El botón ----------------------------------------------------------

    public function test_mi_perfil_trae_el_boton(): void
    {
        config(['webpush.vapid.public_key' => 'clave-publica']);

        $this->actingAs(User::factory()->create())
            ->get(route('profile.edit'))
            ->assertSee('data-push', escape: false)
            ->assertSee('clave-publica');
    }

    // --- El ofrecimiento de la primera vez --------------------------------

    public function test_el_panel_ofrece_activarlos_una_vez(): void
    {
        config(['webpush.vapid.public_key' => 'clave-publica']);

        $this->actingAs(User::factory()->create())
            ->get(route('projects.index'))
            ->assertSee('data-push-invite', escape: false)
            ->assertSee('¿Desea activar notificaciones?');
    }

    /**
     * El permiso del navegador se pide recién al tocar «Activar». Si se
     * pidiera al cargar, Chrome y Firefox lo bloquean solos y después no hay
     * forma de volver a preguntar.
     */
    public function test_el_permiso_no_se_pide_al_cargar_la_pagina(): void
    {
        config(['webpush.vapid.public_key' => 'clave-publica']);

        $html = $this->actingAs(User::factory()->create())
            ->get(route('projects.index'))
            ->getContent();

        $pedido = strpos($html, 'requestPermission');
        $clic   = strpos($html, "addEventListener('click'");

        $this->assertNotFalse($pedido);
        $this->assertNotFalse($clic);
        // requestPermission vive adentro de activar(), que solo se llama desde
        // el clic: nunca suelto en la raíz del script.
        $this->assertStringNotContainsString('ghPush.activar();', $html);
    }

    public function test_sin_claves_no_se_ofrece_nada(): void
    {
        config(['webpush.vapid.public_key' => null]);

        $this->actingAs(User::factory()->create())
            ->get(route('projects.index'))
            ->assertDontSee('¿Desea activar notificaciones?');
    }

    /** La pantalla de clave obligatoria es una puerta: ahí no se ofrece nada. */
    public function test_no_se_ofrece_antes_de_cambiar_la_clave(): void
    {
        config(['webpush.vapid.public_key' => 'clave-publica']);

        $this->actingAs(User::factory()->create(['must_change_password' => true]))
            ->get(route('password.change'))
            ->assertOk()
            ->assertDontSee('data-push-invite', escape: false);
    }

    /**
     * El atributo hidden tiene que ganar siempre.
     *
     * El navegador trae [hidden]{display:none}, pero cualquier regla de autor
     * que declare display le gana: .btn{display:inline-flex} dejaba visible
     * un botón escondido desde JS, como un recuadro vacío que no respondía.
     */
    public function test_el_css_hace_valer_el_atributo_hidden(): void
    {
        $css = file_get_contents(public_path('css/goharv.css'));

        $this->assertStringContainsString('[hidden]{display:none!important}', $css);
    }

    /** La clave privada no puede salir nunca del servidor. */
    public function test_la_clave_privada_no_llega_al_navegador(): void
    {
        config([
            'webpush.vapid.public_key'  => 'clave-publica',
            'webpush.vapid.private_key' => 'clave-privada-secreta',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('profile.edit'))
            ->assertDontSee('clave-privada-secreta');
    }
}
