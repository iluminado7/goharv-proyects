<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El historial es la razon de ser del panel: si moveTo() deja de escribir,
 * se pierde el rastro de quien movio que.
 */
class ProjectHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_move_to_escribe_el_paso_de_un_estado_a_otro(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->status(ProjectStatus::Nuevo)->create();

        $this->assertTrue($project->moveTo(ProjectStatus::Inicio, $user));

        $update = $project->updates()->first();

        $this->assertSame(ProjectStatus::Nuevo, $update->status_from);
        $this->assertSame(ProjectStatus::Inicio, $update->status_to);
        $this->assertSame($user->id, $update->user_id);
        $this->assertNotNull($project->fresh()->started_at);
    }

    public function test_move_to_no_ensucia_el_historial_si_el_estado_no_cambia(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->status(ProjectStatus::Inicio)->create();

        $this->assertFalse($project->moveTo(ProjectStatus::Inicio, $user));
        $this->assertSame(0, $project->updates()->count());
    }

    public function test_move_to_al_mismo_estado_con_nota_si_queda_registrado(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->status(ProjectStatus::EnDesarrollo)->create();

        $this->assertTrue($project->moveTo(ProjectStatus::EnDesarrollo, $user, 'Esperando el diseño.'));
        $this->assertSame('Esperando el diseño.', $project->updates()->first()->body);
    }

    public function test_terminar_y_reabrir_mueve_completed_at(): void
    {
        $project = Project::factory()->status(ProjectStatus::EnDesarrollo)->create();

        $project->moveTo(ProjectStatus::Terminado);
        $this->assertNotNull($project->fresh()->completed_at);

        $project->moveTo(ProjectStatus::EnDesarrollo);
        $this->assertNull($project->fresh()->completed_at);
    }

    public function test_el_atajo_del_tablero_registra_el_movimiento(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->status(ProjectStatus::Nuevo)->create();

        $this->actingAs($user)
            ->patch(route('projects.status', $project), [
                'status' => ProjectStatus::EnDesarrollo->value,
                'note'   => 'Arrancamos.',
            ])
            ->assertRedirect();

        $this->assertSame(ProjectStatus::EnDesarrollo, $project->fresh()->status);
        $this->assertDatabaseHas('project_updates', [
            'project_id' => $project->id,
            'body'       => 'Arrancamos.',
            'status_to'  => ProjectStatus::EnDesarrollo->value,
        ]);
    }
}
