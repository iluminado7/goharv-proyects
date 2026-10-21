<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Las pantallas de tareas. Lo central: viven dentro del proyecto y no se
 * pueden tocar desde otro, ni siquiera armando la URL a mano.
 */
class TaskScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_se_crea_una_tarea_desde_la_ficha(): void
    {
        $duenio = User::factory()->create();
        $colab  = User::factory()->create();

        $project = Project::factory()->ownedBy($duenio)->create();
        $project->collaborators()->attach($colab);

        $this->actingAs($duenio)->post(route('tasks.store', $project), [
            'title'     => 'Mandar la propuesta revisada',
            'due_date'  => now()->addWeek()->toDateString(),
            'assignees' => [$colab->id],
        ])->assertRedirect();

        $task = $project->tasks()->firstOrFail();

        $this->assertSame('Mandar la propuesta revisada', $task->title);
        $this->assertSame($duenio->id, $task->created_by);
        $this->assertSame([$colab->id], $task->assignees->pluck('id')->all());
        $this->assertFalse($task->isCompleted());
    }

    public function test_la_tarea_se_ve_en_la_ficha_de_su_proyecto(): void
    {
        $duenio  = User::factory()->create(['name' => 'Ana Gómez']);
        $project = Project::factory()->ownedBy($duenio)->create();

        $task = Task::factory()->forProject($project)->create(['title' => 'Revisar el contrato']);
        $task->assignTo([$duenio->id]);

        $this->actingAs($duenio)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Revisar el contrato')
            ->assertSee('Ana Gómez')
            ->assertSee('1 pendiente');
    }

    /** No tiene que aparecer en la ficha de otro proyecto. */
    public function test_cada_tarea_se_ve_solo_en_su_proyecto(): void
    {
        $user = User::factory()->create();

        $suyo = Project::factory()->ownedBy($user)->create();
        $otro = Project::factory()->ownedBy($user)->create();

        Task::factory()->forProject($suyo)->create(['title' => 'Tarea del proyecto A']);

        $this->actingAs($user)
            ->get(route('projects.show', $otro))
            ->assertOk()
            ->assertDontSee('Tarea del proyecto A');
    }

    public function test_un_titulo_vacio_no_crea_nada(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();

        $this->actingAs($user)
            ->post(route('tasks.store', $project), ['title' => '   '])
            ->assertSessionHasErrors('title');

        $this->assertSame(0, $project->tasks()->count());
    }

    public function test_no_se_asigna_a_alguien_que_no_esta_en_el_proyecto(): void
    {
        $duenio = User::factory()->create();
        $ajeno  = User::factory()->create();

        $project = Project::factory()->ownedBy($duenio)->create();

        $this->actingAs($duenio)->post(route('tasks.store', $project), [
            'title'     => 'Una tarea',
            'assignees' => [$duenio->id, $ajeno->id],
        ]);

        $this->assertSame([$duenio->id], $project->tasks()->first()->assignees->pluck('id')->all());
    }

    // --- Completar --------------------------------------------------------

    public function test_se_marca_y_se_desmarca(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();
        $task    = Task::factory()->forProject($project)->create();

        $this->actingAs($user)->patch(route('tasks.toggle', [$project, $task]))->assertRedirect();

        $task->refresh();
        $this->assertTrue($task->isCompleted());
        $this->assertSame($user->id, $task->completed_by);

        $this->actingAs($user)->patch(route('tasks.toggle', [$project, $task]));

        $this->assertFalse($task->fresh()->isCompleted());
    }

    public function test_un_colaborador_tambien_puede_completar(): void
    {
        $colab   = User::factory()->create();
        $project = Project::factory()->create();
        $project->collaborators()->attach($colab);

        $task = Task::factory()->forProject($project)->create();

        $this->actingAs($colab)->patch(route('tasks.toggle', [$project, $task]))->assertRedirect();

        $this->assertTrue($task->fresh()->isCompleted());
    }

    public function test_un_ajeno_al_proyecto_no_completa(): void
    {
        $ajeno   = User::factory()->create();
        $project = Project::factory()->create();
        $task    = Task::factory()->forProject($project)->create();

        $this->actingAs($ajeno)
            ->patch(route('tasks.toggle', [$project, $task]))
            ->assertForbidden();

        $this->assertFalse($task->fresh()->isCompleted());
    }

    // --- Eliminar ---------------------------------------------------------

    public function test_quien_la_creo_la_elimina(): void
    {
        $colab   = User::factory()->create();
        $project = Project::factory()->create();
        $project->collaborators()->attach($colab);

        $task = Task::factory()->forProject($project)->create(['created_by' => $colab->id]);

        $this->actingAs($colab)
            ->delete(route('tasks.destroy', [$project, $task]))
            ->assertRedirect();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_el_responsable_del_proyecto_elimina_cualquiera(): void
    {
        $duenio  = User::factory()->create();
        $otro    = User::factory()->create();
        $project = Project::factory()->ownedBy($duenio)->create();

        $task = Task::factory()->forProject($project)->create(['created_by' => $otro->id]);

        $this->actingAs($duenio)
            ->delete(route('tasks.destroy', [$project, $task]))
            ->assertRedirect();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    /** Borrar no se deshace: un colaborador no borra lo que no creó. */
    public function test_un_colaborador_no_borra_tareas_ajenas(): void
    {
        $colab   = User::factory()->create();
        $otro    = User::factory()->create();
        $project = Project::factory()->create();
        $project->collaborators()->attach($colab);

        $task = Task::factory()->forProject($project)->create(['created_by' => $otro->id]);

        $this->actingAs($colab)
            ->delete(route('tasks.destroy', [$project, $task]))
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    // --- La tarea pertenece a su proyecto ---------------------------------

    public function test_no_se_toca_una_tarea_desde_otro_proyecto(): void
    {
        $user = User::factory()->create();

        $suyo = Project::factory()->ownedBy($user)->create();
        $otro = Project::factory()->ownedBy($user)->create();

        $task = Task::factory()->forProject($otro)->create();

        // La URL mezcla un proyecto con una tarea que no es suya.
        $this->actingAs($user)->patch(route('tasks.toggle', [$suyo, $task]))->assertNotFound();
        $this->actingAs($user)->delete(route('tasks.destroy', [$suyo, $task]))->assertNotFound();

        $this->assertFalse($task->fresh()->isCompleted());
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_el_formulario_solo_ofrece_gente_del_proyecto(): void
    {
        $duenio = User::factory()->create(['name' => 'Dueño Pérez']);
        $colab  = User::factory()->create(['name' => 'Colega Díaz']);
        User::factory()->create(['name' => 'Ajeno Suárez']);

        $project = Project::factory()->ownedBy($duenio)->create();
        $project->collaborators()->attach($colab);

        $this->actingAs($duenio)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Dueño Pérez')
            ->assertSee('Colega Díaz')
            ->assertDontSee('Ajeno Suárez');
    }

    public function test_quien_no_participa_ve_las_tareas_pero_no_las_toca(): void
    {
        $ajeno   = User::factory()->create();
        $project = Project::factory()->create();

        Task::factory()->forProject($project)->create(['title' => 'Tarea visible']);

        $this->actingAs($ajeno)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Tarea visible')
            ->assertDontSee('Agregar tarea');
    }
}
