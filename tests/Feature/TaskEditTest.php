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
 * Editar una tarea ya creada. Antes la única salida era eliminarla y hacerla
 * de nuevo, perdiendo quién la pidió y cuándo.
 */
class TaskEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_se_cambia_el_texto_la_fecha_y_a_quien_le_toca(): void
    {
        $duenio = User::factory()->create();
        $uno    = User::factory()->create();
        $dos    = User::factory()->create();

        $project = Project::factory()->ownedBy($duenio)->create();
        $project->collaborators()->attach([$uno->id, $dos->id]);

        $task = Task::factory()->forProject($project)->dueOn(null)->create([
            'title' => 'Titulo con error', 'created_by' => $duenio->id,
        ]);
        $task->assignTo([$uno->id]);

        $this->actingAs($duenio)->put(route('tasks.update', [$project, $task]), [
            'title'     => 'Título corregido',
            'due_date'  => '2026-12-01',
            'assignees' => [$dos->id],
        ])->assertRedirect();

        $task->refresh();

        $this->assertSame('Título corregido', $task->title);
        $this->assertSame('2026-12-01', $task->due_date->toDateString());
        $this->assertSame([$dos->id], $task->assignees->pluck('id')->all());
    }

    public function test_editar_no_pierde_quien_la_pidio_ni_su_estado(): void
    {
        $duenio = User::factory()->create();
        $otro   = User::factory()->create();

        $project = Project::factory()->ownedBy($duenio)->create();
        $task    = Task::factory()->forProject($project)->completed($otro)->create([
            'title' => 'Vieja', 'created_by' => $otro->id,
        ]);

        $this->actingAs($duenio)->put(route('tasks.update', [$project, $task]), [
            'title' => 'Nueva',
        ]);

        $task->refresh();

        $this->assertSame($otro->id, $task->created_by);
        $this->assertTrue($task->isCompleted(), 'Editar el texto no la reabre');
        $this->assertSame($otro->id, $task->completed_by);
    }

    public function test_se_puede_sacar_la_fecha_limite(): void
    {
        $duenio  = User::factory()->create();
        $project = Project::factory()->ownedBy($duenio)->create();
        $task    = Task::factory()->forProject($project)->dueOn('2026-12-01')->create();

        $this->actingAs($duenio)->put(route('tasks.update', [$project, $task]), [
            'title' => $task->title,
        ]);

        $this->assertNull($task->fresh()->due_date);
    }

    // --- Quién puede ------------------------------------------------------

    public function test_quien_la_creo_la_edita(): void
    {
        $colab   = User::factory()->create();
        $project = Project::factory()->create();
        $project->collaborators()->attach($colab);

        $task = Task::factory()->forProject($project)->create(['created_by' => $colab->id]);

        $this->actingAs($colab)
            ->put(route('tasks.update', [$project, $task]), ['title' => 'Arreglada'])
            ->assertRedirect();

        $this->assertSame('Arreglada', $task->fresh()->title);
    }

    /** Editar cambia lo que otro tiene que hacer: no es tildar un casillero. */
    public function test_un_colaborador_no_edita_tareas_ajenas(): void
    {
        $colab   = User::factory()->create();
        $otro    = User::factory()->create();
        $project = Project::factory()->create();
        $project->collaborators()->attach($colab);

        $task = Task::factory()->forProject($project)->create([
            'title' => 'Intacta', 'created_by' => $otro->id,
        ]);

        $this->actingAs($colab)
            ->put(route('tasks.update', [$project, $task]), ['title' => 'Pisada'])
            ->assertForbidden();

        $this->assertSame('Intacta', $task->fresh()->title);
    }

    public function test_no_se_edita_desde_otro_proyecto(): void
    {
        $user = User::factory()->create();
        $suyo = Project::factory()->ownedBy($user)->create();
        $otro = Project::factory()->ownedBy($user)->create();

        $task = Task::factory()->forProject($otro)->create(['title' => 'Intacta']);

        $this->actingAs($user)
            ->put(route('tasks.update', [$suyo, $task]), ['title' => 'Pisada'])
            ->assertNotFound();

        $this->assertSame('Intacta', $task->fresh()->title);
    }

    public function test_no_se_asigna_a_alguien_de_afuera_del_proyecto(): void
    {
        $duenio  = User::factory()->create();
        $ajeno   = User::factory()->create();
        $project = Project::factory()->ownedBy($duenio)->create();
        $task    = Task::factory()->forProject($project)->create(['created_by' => $duenio->id]);

        $this->actingAs($duenio)->put(route('tasks.update', [$project, $task]), [
            'title'     => $task->title,
            'assignees' => [$ajeno->id],
        ]);

        $this->assertCount(0, $task->fresh()->assignees);
    }

    // --- Avisos y bitácora ------------------------------------------------

    public function test_al_sumar_a_alguien_le_llega_el_aviso(): void
    {
        $duenio = User::factory()->create();
        $nuevo  = User::factory()->create();
        $viejo  = User::factory()->create();

        $project = Project::factory()->ownedBy($duenio)->create();
        $project->collaborators()->attach([$nuevo->id, $viejo->id]);

        $task = Task::factory()->forProject($project)->create(['created_by' => $duenio->id]);
        $task->assignTo([$viejo->id]);

        $this->actingAs($duenio)->put(route('tasks.update', [$project, $task]), [
            'title'     => $task->title,
            'assignees' => [$viejo->id, $nuevo->id],
        ]);

        $this->assertSame(
            [NotificationType::TareaAsignada->value],
            $nuevo->fresh()->notifications->pluck('data.tipo')->all()
        );
        $this->assertCount(0, $viejo->fresh()->notifications, 'El que ya la tenía no recibe nada');
    }

    public function test_guardar_sin_tocar_la_lista_no_avisa(): void
    {
        $duenio = User::factory()->create();
        $colab  = User::factory()->create();

        $project = Project::factory()->ownedBy($duenio)->create();
        $project->collaborators()->attach($colab);

        $task = Task::factory()->forProject($project)->create(['created_by' => $duenio->id]);
        $task->assignTo([$colab->id]);

        $this->actingAs($duenio)->put(route('tasks.update', [$project, $task]), [
            'title'     => 'Otro título',
            'assignees' => [$colab->id],
        ]);

        $this->assertCount(0, $colab->fresh()->notifications);
    }

    public function test_la_edicion_queda_en_la_bitacora(): void
    {
        $duenio  = User::factory()->create();
        $project = Project::factory()->ownedBy($duenio)->create();
        $task    = Task::factory()->forProject($project)->create(['created_by' => $duenio->id]);

        $this->actingAs($duenio)->put(route('tasks.update', [$project, $task]), [
            'title' => 'El título nuevo',
        ]);

        $log = Activity::where('action', ActivityAction::TareaEditada->value)->firstOrFail();

        $this->assertSame('El título nuevo', $log->detail);
        $this->assertSame($duenio->id, $log->user_id);
    }

    // --- El límite de caracteres -------------------------------------------

    public function test_entran_titulos_largos(): void
    {
        $duenio  = User::factory()->create();
        $project = Project::factory()->ownedBy($duenio)->create();

        $largo = str_repeat('a', 500);

        $this->actingAs($duenio)
            ->post(route('tasks.store', $project), ['title' => $largo])
            ->assertRedirect();

        $this->assertSame($largo, $project->tasks()->first()->title);
    }

    public function test_pasado_el_tope_avisa_en_vez_de_cortar(): void
    {
        $duenio  = User::factory()->create();
        $project = Project::factory()->ownedBy($duenio)->create();

        $this->actingAs($duenio)
            ->post(route('tasks.store', $project), ['title' => str_repeat('a', 501)])
            ->assertSessionHasErrors('title');

        $this->assertSame(0, $project->tasks()->count());
    }

    public function test_el_formulario_de_edicion_solo_lo_ve_quien_puede(): void
    {
        $duenio = User::factory()->create();
        $colab  = User::factory()->create();

        $project = Project::factory()->ownedBy($duenio)->create();
        $project->collaborators()->attach($colab);

        Task::factory()->forProject($project)->create(['created_by' => $duenio->id]);

        $this->actingAs($duenio)
            ->get(route('projects.show', $project))
            ->assertSee('tarea-editar', escape: false);

        $this->actingAs($colab)
            ->get(route('projects.show', $project))
            ->assertDontSee('tarea-editar', escape: false);
    }
}
