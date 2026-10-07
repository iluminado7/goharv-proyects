<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La campana avisa lo que otro te hizo en un proyecto donde estás. Nunca tus
 * propias acciones, nunca proyectos ajenos: si suena por todo, nadie la mira.
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function tipos(User $user): array
    {
        return $user->notifications->pluck('data.tipo')->all();
    }

    public function test_avisa_a_quien_queda_a_cargo(): void
    {
        $jefe   = User::factory()->admin()->create();
        $duenio = User::factory()->create();

        $this->actingAs($jefe)->post(route('projects.store'), [
            'name'     => 'Proyecto asignado',
            'status'   => ProjectStatus::Nuevo->value,
            'priority' => ProjectPriority::Media->value,
            'owner_id' => $duenio->id,
        ]);

        $this->assertSame([NotificationType::TeAsignaron->value], $this->tipos($duenio->fresh()));
    }

    public function test_avisa_a_los_colaboradores_recien_sumados(): void
    {
        $duenio = User::factory()->create();
        $nuevo  = User::factory()->create();
        $viejo  = User::factory()->create();

        $project = Project::factory()->ownedBy($duenio)->create();
        $project->collaborators()->attach($viejo);

        $this->actingAs($duenio)->put(route('projects.update', $project), [
            'name'          => $project->name,
            'status'        => $project->status->value,
            'priority'      => $project->priority->value,
            'owner_id'      => $duenio->id,
            'collaborators' => [$viejo->id, $nuevo->id],
        ]);

        $this->assertSame([NotificationType::TeSumaron->value], $this->tipos($nuevo->fresh()));
        $this->assertCount(0, $viejo->fresh()->notifications, 'El que ya estaba no recibe nada');
    }

    public function test_el_comentario_le_llega_al_resto_pero_no_al_autor(): void
    {
        $duenio = User::factory()->create();
        $colab  = User::factory()->create();
        $ajeno  = User::factory()->create();

        $project = Project::factory()->ownedBy($duenio)->create();
        $project->collaborators()->attach($colab);

        $this->actingAs($colab)->post(route('projects.comment', $project), [
            'body' => 'Esto lo escribo yo.',
        ]);

        $this->assertSame([NotificationType::Comentario->value], $this->tipos($duenio->fresh()));
        $this->assertCount(0, $colab->fresh()->notifications, 'Nadie recibe aviso de lo suyo');
        $this->assertCount(0, $ajeno->fresh()->notifications, 'Ni los que no participan');
    }

    public function test_el_cambio_de_estado_avisa_con_el_de_donde_a_donde(): void
    {
        $duenio = User::factory()->create();
        $colab  = User::factory()->create();

        $project = Project::factory()->ownedBy($duenio)->status(ProjectStatus::Nuevo)->create();
        $project->collaborators()->attach($colab);

        $this->actingAs($colab)->patch(route('projects.status', $project), [
            'status' => ProjectStatus::EnDesarrollo->value,
        ]);

        $aviso = $duenio->fresh()->notifications->first();

        $this->assertSame(NotificationType::CambioEstado->value, $aviso->data['tipo']);
        $this->assertSame('Nuevo → En desarrollo', $aviso->data['detalle']);
        $this->assertCount(0, $colab->fresh()->notifications);
    }

    public function test_una_nota_sin_mover_el_estado_no_avisa_dos_veces(): void
    {
        $duenio = User::factory()->create();
        $colab  = User::factory()->create();

        $project = Project::factory()->ownedBy($duenio)->status(ProjectStatus::Inicio)->create();
        $project->collaborators()->attach($colab);

        $this->actingAs($colab)->patch(route('projects.status', $project), [
            'status' => ProjectStatus::Inicio->value,
            'note'   => 'Seguimos esperando.',
        ]);

        $this->assertCount(0, $duenio->fresh()->notifications);
    }

    public function test_las_cuentas_de_baja_no_reciben_avisos(): void
    {
        $duenio = User::factory()->inactive()->create();
        $otro   = User::factory()->create();
        $project = Project::factory()->ownedBy($duenio)->create();

        $this->actingAs($otro)->post(route('projects.comment', $project), ['body' => 'Hola.']);

        $this->assertCount(0, $duenio->fresh()->notifications);
    }

    public function test_guardar_sin_tocar_nada_no_genera_avisos(): void
    {
        $duenio = User::factory()->create();
        $colab  = User::factory()->create();

        $project = Project::factory()->ownedBy($duenio)->create();
        $project->collaborators()->attach($colab);

        $this->actingAs($duenio)->put(route('projects.update', $project), [
            'name'          => $project->name,
            'status'        => $project->status->value,
            'priority'      => $project->priority->value,
            'owner_id'      => $duenio->id,
            'collaborators' => [$colab->id],
        ]);

        $this->assertCount(0, $colab->fresh()->notifications);
    }

    // --- La campana y la pantalla ----------------------------------------

    public function test_la_campana_muestra_cuantas_hay_sin_leer(): void
    {
        $duenio = User::factory()->create();
        $otro   = User::factory()->create();
        $project = Project::factory()->ownedBy($duenio)->create();

        $this->actingAs($otro)->post(route('projects.comment', $project), ['body' => 'Una.']);
        $this->actingAs($otro)->post(route('projects.comment', $project), ['body' => 'Dos.']);

        $this->actingAs($duenio)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('campana-n', escape: false)
            ->assertSee('>2</span>', escape: false);
    }

    public function test_la_campana_abre_un_cuadro_con_las_ultimas(): void
    {
        $duenio  = User::factory()->create();
        $otro    = User::factory()->create(['name' => 'Ana Gómez']);
        $project = Project::factory()->ownedBy($duenio)->create(['name' => 'Portal de reclamos']);

        $this->actingAs($otro)->post(route('projects.comment', $project), ['body' => 'Mirá esto.']);

        $html = $this->actingAs($duenio)->get(route('projects.index'))->getContent();

        // El cuadro viene en la pagina, no hace falta navegar a otro lado.
        $this->assertStringContainsString('campana-panel', $html);
        $this->assertStringContainsString('Portal de reclamos', $html);
        $this->assertStringContainsString('Ana Gómez', $html);
        $this->assertStringContainsString('Ver todas', $html);
    }

    /** <details> es HTML puro: el cuadro abre y cierra aunque no corra el JS. */
    public function test_el_cuadro_no_depende_de_javascript(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get(route('projects.index'))
            ->getContent();

        $this->assertStringContainsString('<details class="campana-caja">', $html);
        $this->assertStringContainsString('<summary class="campana', $html);
    }

    public function test_el_cuadro_vacio_lo_dice(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('projects.index'))
            ->assertSee('Sin notificaciones pendientes');
    }

    public function test_sin_avisos_no_hay_contador(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('projects.index'))
            ->assertOk()
            ->assertDontSee('campana-n', escape: false);
    }

    public function test_cada_uno_ve_solo_sus_avisos(): void
    {
        $duenio = User::factory()->create();
        $otro   = User::factory()->create();
        $project = Project::factory()->ownedBy($duenio)->create(['name' => 'Proyecto del dueño']);

        $this->actingAs($otro)->post(route('projects.comment', $project), ['body' => 'Mirá esto.']);

        $this->actingAs($duenio)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Proyecto del dueño');

        $this->actingAs($otro)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertDontSee('Proyecto del dueño');
    }

    public function test_se_marcan_todas_como_leidas(): void
    {
        $duenio = User::factory()->create();
        $otro   = User::factory()->create();
        $project = Project::factory()->ownedBy($duenio)->create();

        $this->actingAs($otro)->post(route('projects.comment', $project), ['body' => 'Una.']);

        $this->actingAs($duenio)->post(route('notifications.read-all'))->assertRedirect();

        $this->assertCount(0, $duenio->fresh()->unreadNotifications);
        $this->assertCount(1, $duenio->fresh()->notifications, 'Se marcan, no se borran');
    }

    /** El nombre del proyecto queda guardado en el aviso. */
    public function test_el_aviso_sobrevive_al_borrado_del_proyecto(): void
    {
        $admin  = User::factory()->admin()->create();
        $duenio = User::factory()->create();
        $project = Project::factory()->ownedBy($duenio)->create(['name' => 'Proyecto que se fue']);

        $this->actingAs($admin)->post(route('projects.comment', $project), ['body' => 'Última nota.']);

        $project->delete();
        $this->actingAs($admin)->delete(route('projects.force-destroy', $project), [
            'confirmacion' => 'Proyecto que se fue',
        ]);

        $this->actingAs($duenio)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Proyecto que se fue');
    }
}
