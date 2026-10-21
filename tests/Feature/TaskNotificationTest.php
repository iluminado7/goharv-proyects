<?php

namespace Tests\Feature;

use App\Enums\ActivityAction;
use App\Enums\NotificationType;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Las tareas en la campana y en la bitácora. La regla de la campana sigue
 * siendo la de siempre: otro te lo hizo y podés hacer algo al respecto.
 */
class TaskNotificationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Project, 1: User, 2: User} */
    private function proyectoCon(): array
    {
        $duenio = User::factory()->create();
        $colab  = User::factory()->create();

        $project = Project::factory()->ownedBy($duenio)->create();
        $project->collaborators()->attach($colab);

        return [$project, $duenio, $colab];
    }

    private function tipos(User $user): array
    {
        return $user->fresh()->notifications->pluck('data.tipo')->all();
    }

    // --- Asignación -------------------------------------------------------

    public function test_a_quien_le_asignan_una_tarea_se_entera(): void
    {
        [$project, $duenio, $colab] = $this->proyectoCon();

        $this->actingAs($duenio)->post(route('tasks.store', $project), [
            'title'     => 'Mandar la propuesta',
            'due_date'  => now()->addWeek()->toDateString(),
            'assignees' => [$colab->id],
        ]);

        $aviso = $colab->fresh()->notifications->first();

        $this->assertSame(NotificationType::TareaAsignada->value, $aviso->data['tipo']);
        $this->assertSame('Mandar la propuesta', $aviso->data['detalle']);
        $this->assertSame($project->name, $aviso->data['proyecto']);
        $this->assertSame($duenio->name, $aviso->data['actor']);
    }

    public function test_quien_la_crea_no_se_avisa_a_si_mismo(): void
    {
        [$project, $duenio, $colab] = $this->proyectoCon();

        $this->actingAs($duenio)->post(route('tasks.store', $project), [
            'title'     => 'Una tarea para los dos',
            'assignees' => [$duenio->id, $colab->id],
        ]);

        $this->assertCount(0, $duenio->fresh()->notifications);
        $this->assertCount(1, $colab->fresh()->notifications);
    }

    public function test_una_tarea_sin_asignar_no_avisa_a_nadie(): void
    {
        [$project, $duenio, $colab] = $this->proyectoCon();

        $this->actingAs($duenio)->post(route('tasks.store', $project), ['title' => 'Sin dueño']);

        $this->assertCount(0, $colab->fresh()->notifications);
    }

    public function test_las_cuentas_de_baja_no_reciben_tareas(): void
    {
        $duenio = User::factory()->create();
        $baja   = User::factory()->inactive()->create();

        $project = Project::factory()->ownedBy($duenio)->create();
        $project->collaborators()->attach($baja);

        $this->actingAs($duenio)->post(route('tasks.store', $project), [
            'title'     => 'Algo',
            'assignees' => [$baja->id],
        ]);

        $this->assertCount(0, $baja->fresh()->notifications);
    }

    // --- Completar --------------------------------------------------------

    public function test_completarla_avisa_al_resto_de_los_asignados(): void
    {
        [$project, $duenio, $colab] = $this->proyectoCon();

        $task = Task::factory()->forProject($project)->create(['created_by' => $duenio->id]);
        $task->assignTo([$duenio->id, $colab->id]);

        $this->actingAs($colab)->patch(route('tasks.toggle', [$project, $task]));

        $this->assertSame([NotificationType::TareaHecha->value], $this->tipos($duenio));
        $this->assertCount(0, $colab->fresh()->notifications, 'Quien la completó no se avisa');
    }

    /** Aunque no sea uno de los asignados: la pidió y quiere saber. */
    public function test_tambien_le_llega_a_quien_la_creo(): void
    {
        [$project, $duenio, $colab] = $this->proyectoCon();

        $task = Task::factory()->forProject($project)->create(['created_by' => $duenio->id]);
        $task->assignTo([$colab->id]);

        $this->actingAs($colab)->patch(route('tasks.toggle', [$project, $task]));

        $this->assertSame([NotificationType::TareaHecha->value], $this->tipos($duenio));
    }

    public function test_reabrirla_no_avisa(): void
    {
        [$project, $duenio, $colab] = $this->proyectoCon();

        $task = Task::factory()->forProject($project)->completed($duenio)->create(['created_by' => $duenio->id]);
        $task->assignTo([$duenio->id, $colab->id]);

        $this->actingAs($colab)->patch(route('tasks.toggle', [$project, $task]));

        $this->assertCount(0, $duenio->fresh()->notifications, 'Pasa poco y no requiere acción');
    }

    // --- Bitácora ---------------------------------------------------------

    public function test_las_cuatro_acciones_quedan_en_la_bitacora(): void
    {
        [$project, $duenio] = $this->proyectoCon();

        $this->actingAs($duenio)->post(route('tasks.store', $project), ['title' => 'Revisar el contrato']);
        $task = $project->tasks()->firstOrFail();

        $this->actingAs($duenio)->patch(route('tasks.toggle', [$project, $task]));
        $this->actingAs($duenio)->patch(route('tasks.toggle', [$project, $task]));
        $this->actingAs($duenio)->delete(route('tasks.destroy', [$project, $task]));

        $this->assertSame([
            ActivityAction::TareaCreada,
            ActivityAction::TareaCompletada,
            ActivityAction::TareaReabierta,
            ActivityAction::TareaEliminada,
        ], Activity::orderBy('id')->pluck('action')->all());

        // El título va en todas: sin él el registro no se entiende.
        $this->assertTrue(
            Activity::all()->every(fn ($a) => $a->detail === 'Revisar el contrato')
        );
    }

    /** El registro del borrado tiene que sobrevivir al borrado. */
    public function test_el_titulo_queda_aunque_la_tarea_ya_no_exista(): void
    {
        [$project, $duenio] = $this->proyectoCon();

        $task = Task::factory()->forProject($project)->create([
            'title' => 'Tarea que se fue', 'created_by' => $duenio->id,
        ]);

        $this->actingAs($duenio)->delete(route('tasks.destroy', [$project, $task]));

        $log = Activity::where('action', ActivityAction::TareaEliminada->value)->firstOrFail();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
        $this->assertSame('Tarea que se fue', $log->detail);
        $this->assertSame($project->id, $log->project_id);
    }

    public function test_el_aviso_de_tarea_lleva_al_proyecto(): void
    {
        [$project, $duenio, $colab] = $this->proyectoCon();

        $this->actingAs($duenio)->post(route('tasks.store', $project), [
            'title'     => 'Llamar al proveedor',
            'assignees' => [$colab->id],
        ]);

        $this->actingAs($colab)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Te asignaron una tarea')
            ->assertSee(route('projects.show', $project), escape: false);
    }
}
