<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\Bienvenida;
use App\Notifications\ProjectEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Por correo salen solo los tres avisos que dicen "esto ahora es tuyo". Los
 * otros tres quedan en la campana: en un proyecto activo son varios por día y
 * por correo se vuelven ruido.
 */
class MailNotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Las claves VAPID se fijan a mano: si no, el resultado dependería de si
     * quien corre los tests las tiene cargadas en su .env.
     */
    private function canales(NotificationType $tipo, bool $conPush = false): array
    {
        config(['webpush.vapid.public_key' => $conPush ? 'clave-publica' : null]);
        config(['webpush.vapid.private_key' => $conPush ? 'clave-privada' : null]);

        $project = Project::factory()->create();

        return (new ProjectEvent($tipo, $project))->via(User::factory()->make());
    }

    public function test_los_tres_avisos_accionables_van_por_correo(): void
    {
        foreach ([NotificationType::TeAsignaron, NotificationType::TeSumaron,
                  NotificationType::TareaAsignada] as $tipo) {
            $this->assertSame(['database', 'mail'], $this->canales($tipo), $tipo->value);
        }
    }

    /** Los mismos tres van al celular cuando el push está configurado. */
    public function test_los_tres_tambien_van_al_celular(): void
    {
        foreach ([NotificationType::TeAsignaron, NotificationType::TeSumaron,
                  NotificationType::TareaAsignada] as $tipo) {
            $this->assertSame(['database', 'mail', 'webpush'], $this->canales($tipo, true), $tipo->value);
        }
    }

    /** Sin claves VAPID el canal reventaría al construirse: no se suma. */
    public function test_sin_claves_vapid_no_se_intenta_el_push(): void
    {
        $this->assertNotContains('webpush', $this->canales(NotificationType::TeAsignaron));
    }

    public function test_los_informativos_tampoco_van_al_celular(): void
    {
        $this->assertSame(['database'], $this->canales(NotificationType::Comentario, true));
    }

    public function test_los_informativos_se_quedan_en_la_campana(): void
    {
        foreach ([NotificationType::Comentario, NotificationType::CambioEstado,
                  NotificationType::TareaHecha] as $tipo) {
            $this->assertSame(['database'], $this->canales($tipo), $tipo->value);
        }
    }

    /** La campana va primero: si el correo se cae, el aviso ya quedó guardado. */
    public function test_la_campana_va_antes_que_el_correo(): void
    {
        $canales = $this->canales(NotificationType::TeAsignaron);

        $this->assertSame('database', $canales[0]);
    }

    // --- Que salgan de verdad desde las acciones del panel ----------------

    public function test_asignar_un_proyecto_manda_correo(): void
    {
        Notification::fake();

        $admin  = User::factory()->admin()->create();
        $duenio = User::factory()->create();

        $this->actingAs($admin)->post(route('projects.store'), [
            'name' => 'Proyecto nuevo', 'status' => 'nuevo',
            'priority' => 'media', 'owner_id' => $duenio->id,
        ]);

        Notification::assertSentTo($duenio, ProjectEvent::class,
            fn (ProjectEvent $a, array $canales) => in_array('mail', $canales, true)
                && $a->tipo === NotificationType::TeAsignaron);
    }

    public function test_asignar_una_tarea_manda_correo(): void
    {
        Notification::fake();

        $duenio = User::factory()->create();
        $colab  = User::factory()->create();

        $project = Project::factory()->ownedBy($duenio)->create();
        $project->collaborators()->attach($colab);

        $this->actingAs($duenio)->post(route('tasks.store', $project), [
            'title' => 'Mandar la propuesta', 'assignees' => [$colab->id],
        ]);

        Notification::assertSentTo($colab, ProjectEvent::class,
            fn (ProjectEvent $a, array $canales) => in_array('mail', $canales, true));
    }

    public function test_un_comentario_no_manda_correo(): void
    {
        Notification::fake();

        $duenio = User::factory()->create();
        $colab  = User::factory()->create();

        $project = Project::factory()->ownedBy($duenio)->create();
        $project->collaborators()->attach($colab);

        $this->actingAs($duenio)->post(route('projects.comment', $project), [
            'body' => 'Una nota cualquiera',
        ]);

        Notification::assertSentTo($colab, ProjectEvent::class,
            fn (ProjectEvent $a, array $canales) => ! in_array('mail', $canales, true));
    }

    // --- El correo no puede voltear la acción ------------------------------

    /**
     * El correo sale en el mismo request y depende de un servicio de afuera.
     * Si Resend se cae, lo que no puede pasar es perder el proyecto que
     * alguien acaba de guardar.
     */
    public function test_si_el_correo_falla_la_accion_se_guarda_igual(): void
    {
        Notification::shouldReceive('send')
            ->andThrow(new \RuntimeException('Resend caído'));

        $admin  = User::factory()->admin()->create();
        $duenio = User::factory()->create();

        $this->actingAs($admin)->post(route('projects.store'), [
            'name' => 'Igual se guarda', 'status' => 'nuevo',
            'priority' => 'media', 'owner_id' => $duenio->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('projects', ['name' => 'Igual se guarda']);
    }

    // --- Bienvenida --------------------------------------------------------

    public function test_crear_una_cuenta_manda_la_bienvenida(): void
    {
        Notification::fake();

        $this->actingAs(User::factory()->admin()->create())->post(route('members.store'), [
            'name' => 'Estudio Contable', 'email' => 'estudio@ejemplo.com',
            'password' => 'una-clave-larga-y-rara', 'role' => 'external',
        ]);

        $nuevo = User::where('email', 'estudio@ejemplo.com')->firstOrFail();

        Notification::assertSentTo($nuevo, Bienvenida::class,
            fn (Bienvenida $a) => $a->clave === 'una-clave-larga-y-rara');
    }

    /**
     * Mientras no haya un dominio verificado, el panel corre con
     * MAIL_MAILER=log y no le llega nada a nadie. El alta no puede decir "le
     * mandamos los datos" cuando eso no pasó: el responsable tiene que saber
     * que la clave se la pasa él.
     */
    public function test_sin_correo_configurado_el_alta_no_promete_un_mail(): void
    {
        config(['mail.default' => 'log']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('members.store'), [
                'name' => 'Estudio Contable', 'email' => 'estudio@ejemplo.com',
                'password' => 'una-clave-larga-y-rara', 'role' => 'external',
            ])
            ->assertSessionHas('ok', fn (string $m) => str_contains($m, 'pasale vos'));
    }

    public function test_con_correo_configurado_si_lo_promete(): void
    {
        Notification::fake();
        config(['mail.default' => 'resend']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('members.store'), [
                'name' => 'Estudio Contable', 'email' => 'estudio@ejemplo.com',
                'password' => 'una-clave-larga-y-rara', 'role' => 'external',
            ])
            ->assertSessionHas('ok', fn (string $m) => str_contains($m, 'estudio@ejemplo.com'));
    }

    public function test_el_correo_de_bienvenida_lleva_el_usuario_y_la_clave(): void
    {
        $nuevo = User::factory()->create([
            'name' => 'Estudio Contable', 'email' => 'estudio@ejemplo.com',
        ]);

        $html = (new Bienvenida('una-clave-larga-y-rara'))
            ->toMail($nuevo)
            ->render();

        $this->assertStringContainsString('estudio@ejemplo.com', $html);
        $this->assertStringContainsString('una-clave-larga-y-rara', $html);
        $this->assertStringContainsString(route('login'), $html);
    }

    public function test_el_aviso_de_proyecto_lleva_el_enlace_a_la_ficha(): void
    {
        $quien   = User::factory()->create(['name' => 'Juana']);
        $actor   = User::factory()->create(['name' => 'Pedro']);
        $project = Project::factory()->create(['name' => 'Local Rosario']);

        $mail = (new ProjectEvent(NotificationType::TeAsignaron, $project, $actor))
            ->toMail($quien);

        $this->assertStringContainsString('Local Rosario', $mail->subject);

        $html = $mail->render();

        $this->assertStringContainsString('Juana', $html);
        $this->assertStringContainsString('Pedro', $html);
        $this->assertStringContainsString(route('projects.show', $project), $html);
    }

    public function test_el_aviso_de_tarea_lleva_el_titulo(): void
    {
        $quien   = User::factory()->create();
        $project = Project::factory()->create();
        $task    = Task::factory()->forProject($project)->create(['title' => 'Mandar el balance']);

        $html = (new ProjectEvent(NotificationType::TareaAsignada, $project, null, $task->title))
            ->toMail($quien)
            ->render();

        $this->assertStringContainsString('Mandar el balance', $html);
    }
}
