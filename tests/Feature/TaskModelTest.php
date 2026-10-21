<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La capa de datos de las tareas. Las pantallas vienen después; acá se fija
 * cómo se comportan el estado, los asignados y el borrado en cascada.
 */
class TaskModelTest extends TestCase
{
    use RefreshDatabase;

    // --- Los dos estados --------------------------------------------------

    public function test_una_tarea_nace_pendiente(): void
    {
        $task = Task::factory()->create();

        $this->assertFalse($task->isCompleted());
        $this->assertNull($task->completed_at);
    }

    public function test_completar_deja_cuando_y_quien(): void
    {
        $quien = User::factory()->create();
        $task  = Task::factory()->create();

        $this->assertTrue($task->toggle(true, $quien));

        $task->refresh();

        $this->assertTrue($task->isCompleted());
        $this->assertNotNull($task->completed_at);
        $this->assertSame($quien->id, $task->completed_by);
        $this->assertSame($quien->id, $task->completer->id);
    }

    public function test_se_puede_volver_atras(): void
    {
        $task = Task::factory()->completed(User::factory()->create())->create();

        $this->assertTrue($task->toggle(false));

        $task->refresh();

        $this->assertFalse($task->isCompleted());
        $this->assertNull($task->completed_at);
        $this->assertNull($task->completed_by, 'Si vuelve a pendiente, no queda quien la completó');
    }

    /** Para que quien llame no registre un movimiento que no ocurrió. */
    public function test_marcar_lo_que_ya_estaba_marcado_devuelve_false(): void
    {
        $task = Task::factory()->create();

        $this->assertTrue($task->toggle(true, User::factory()->create()));
        $this->assertFalse($task->toggle(true, User::factory()->create()));
    }

    // --- Vencimiento ------------------------------------------------------

    public function test_vencida_es_con_fecha_pasada_y_sin_completar(): void
    {
        $vencida   = Task::factory()->dueOn(now()->subDay()->toDateString())->create();
        $aTiempo   = Task::factory()->dueOn(now()->addWeek()->toDateString())->create();
        $sinFecha  = Task::factory()->dueOn(null)->create();

        $this->assertTrue($vencida->isOverdue());
        $this->assertFalse($aTiempo->isOverdue());
        $this->assertFalse($sinFecha->isOverdue(), 'Sin fecha no se vence nunca');

        $vencida->toggle(true, User::factory()->create());

        $this->assertFalse($vencida->fresh()->isOverdue(), 'Completada ya no está vencida');
    }

    public function test_el_scope_vencidas_coincide(): void
    {
        $project = Project::factory()->create();

        Task::factory()->forProject($project)->dueOn(now()->subDay()->toDateString())->create();
        Task::factory()->forProject($project)->dueOn(now()->addWeek()->toDateString())->create();
        Task::factory()->forProject($project)->dueOn(null)->create();
        Task::factory()->forProject($project)->dueOn(now()->subWeek()->toDateString())
            ->completed(User::factory()->create())->create();

        $this->assertSame(1, Task::vencidas()->count());
    }

    // --- A quién va dirigida ----------------------------------------------

    public function test_se_asigna_a_varios_del_proyecto(): void
    {
        $duenio = User::factory()->create();
        $colab  = User::factory()->create();

        $project = Project::factory()->ownedBy($duenio)->create();
        $project->collaborators()->attach($colab);

        $task = Task::factory()->forProject($project)->create();
        $task->assignTo([$duenio->id, $colab->id]);

        $this->assertEqualsCanonicalizing(
            [$duenio->id, $colab->id],
            $task->assignees->pluck('id')->all()
        );
    }

    /** Una tarea dirigida a alguien de afuera no la vería nadie. */
    public function test_no_se_puede_asignar_a_alguien_ajeno_al_proyecto(): void
    {
        $duenio = User::factory()->create();
        $ajeno  = User::factory()->create();

        $project = Project::factory()->ownedBy($duenio)->create();
        $task    = Task::factory()->forProject($project)->create();

        $task->assignTo([$duenio->id, $ajeno->id]);

        $this->assertSame([$duenio->id], $task->assignees->pluck('id')->all());
    }

    public function test_asignar_de_nuevo_reemplaza_la_lista(): void
    {
        $uno = User::factory()->create();
        $dos = User::factory()->create();

        $project = Project::factory()->ownedBy($uno)->create();
        $project->collaborators()->attach($dos);

        $task = Task::factory()->forProject($project)->create();
        $task->assignTo([$uno->id, $dos->id]);
        $task->assignTo([$dos->id]);

        $this->assertSame([$dos->id], $task->fresh()->assignees->pluck('id')->all());
    }

    public function test_desde_el_usuario_se_ven_sus_tareas(): void
    {
        $user  = User::factory()->create();
        $otro  = User::factory()->create();

        $project = Project::factory()->ownedBy($user)->create();
        $mia     = Task::factory()->forProject($project)->create();
        $mia->assignTo([$user->id]);

        Task::factory()->forProject(Project::factory()->ownedBy($otro)->create())->create();

        $this->assertSame([$mia->id], $user->fresh()->tasks->pluck('id')->all());
        $this->assertSame([$mia->id], Task::for($user)->pluck('id')->all());
    }

    // --- Orden y borrado --------------------------------------------------

    public function test_primero_las_pendientes_y_lo_que_vence_antes(): void
    {
        $project = Project::factory()->create();

        Task::factory()->forProject($project)->dueOn(null)->create(['title' => 'Pendiente sin fecha']);
        Task::factory()->forProject($project)->dueOn(now()->addMonth()->toDateString())->create(['title' => 'Pendiente lejana']);
        Task::factory()->forProject($project)->dueOn(now()->addDay()->toDateString())->create(['title' => 'Pendiente urgente']);
        Task::factory()->forProject($project)->completed(User::factory()->create())->create(['title' => 'Ya completada']);

        $this->assertSame(
            ['Pendiente urgente', 'Pendiente lejana', 'Pendiente sin fecha', 'Ya completada'],
            $project->tasks->pluck('title')->all()
        );
    }

    public function test_archivar_el_proyecto_no_toca_sus_tareas(): void
    {
        $project = Project::factory()->create();
        Task::factory()->forProject($project)->create();

        $project->delete();

        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_borrar_el_proyecto_de_verdad_se_lleva_las_tareas(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();

        $task = Task::factory()->forProject($project)->create();
        $task->assignTo([$user->id]);

        $project->forceDelete();

        $this->assertDatabaseCount('tasks', 0);
        // Los asignados también se van, por la cascada del pivote.
        $this->assertDatabaseCount('task_user', 0);
    }

    public function test_si_se_borra_una_persona_la_tarea_sobrevive(): void
    {
        $quien = User::factory()->create();
        $task  = Task::factory()->create(['created_by' => $quien->id]);
        $task->toggle(true, $quien);

        $quien->forceDelete();

        $task->refresh();

        $this->assertNotNull($task->id);
        $this->assertNull($task->created_by);
        $this->assertNull($task->completed_by);
        $this->assertTrue($task->isCompleted(), 'Sigue completada aunque no se sepa por quién');
    }

    public function test_los_candidatos_son_los_del_proyecto(): void
    {
        $duenio = User::factory()->create();
        $colab  = User::factory()->create();
        User::factory()->create(); // ajeno

        $project = Project::factory()->ownedBy($duenio)->create();
        $project->collaborators()->attach($colab);

        $task = Task::factory()->forProject($project)->create();

        $this->assertEqualsCanonicalizing(
            [$duenio->id, $colab->id],
            $task->candidatos()->pluck('id')->all()
        );
    }
}
