<?php

namespace Tests\Feature;

use App\Enums\ActivityAction;
use App\Enums\ProjectPriority;
use App\Models\Activity;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La prioridad ordena el tablero que mira todo el equipo, así que no la cambia
 * cualquiera que colabore: la decide el responsable del proyecto, y los
 * responsables del panel sobre cualquier proyecto.
 */
class ProjectPriorityTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_responsable_del_proyecto_la_cambia_desde_la_ficha(): void
    {
        $duenio  = User::factory()->create();
        $project = Project::factory()->ownedBy($duenio)->priority(ProjectPriority::Baja)->create();

        $this->actingAs($duenio)
            ->patch(route('projects.priority', $project), ['priority' => ProjectPriority::Alta->value])
            ->assertRedirect();

        $this->assertSame(ProjectPriority::Alta, $project->fresh()->priority);
    }

    public function test_el_responsable_del_panel_la_cambia_en_cualquiera(): void
    {
        $admin   = User::factory()->admin()->create();
        $project = Project::factory()->priority(ProjectPriority::Media)->create();

        $this->actingAs($admin)
            ->patch(route('projects.priority', $project), ['priority' => ProjectPriority::Alta->value])
            ->assertRedirect();

        $this->assertSame(ProjectPriority::Alta, $project->fresh()->priority);
    }

    public function test_un_colaborador_no_la_cambia(): void
    {
        $colab   = User::factory()->create();
        $project = Project::factory()->priority(ProjectPriority::Baja)->create();
        $project->collaborators()->attach($colab);

        $this->actingAs($colab)
            ->patch(route('projects.priority', $project), ['priority' => ProjectPriority::Alta->value])
            ->assertForbidden();

        $this->assertSame(ProjectPriority::Baja, $project->fresh()->priority);
    }

    public function test_un_ajeno_tampoco(): void
    {
        $ajeno   = User::factory()->create();
        $project = Project::factory()->priority(ProjectPriority::Baja)->create();

        $this->actingAs($ajeno)
            ->patch(route('projects.priority', $project), ['priority' => ProjectPriority::Alta->value])
            ->assertForbidden();
    }

    /**
     * Lo que hace real a la regla: un colaborador puede editar el proyecto,
     * así que por el formulario podría colar una prioridad nueva.
     */
    public function test_un_colaborador_no_la_cuela_por_el_formulario_de_edicion(): void
    {
        $colab   = User::factory()->create();
        $project = Project::factory()->priority(ProjectPriority::Baja)->create();
        $project->collaborators()->attach($colab);

        $this->actingAs($colab)->put(route('projects.update', $project), [
            'name'     => 'Nombre nuevo',
            'status'   => $project->status->value,
            'priority' => ProjectPriority::Alta->value,
        ])->assertRedirect();

        $project->refresh();

        // El resto de la edición sí se guarda: solo se descarta la prioridad.
        $this->assertSame('Nombre nuevo', $project->name);
        $this->assertSame(ProjectPriority::Baja, $project->priority);
    }

    public function test_el_cambio_queda_en_la_bitacora(): void
    {
        $duenio  = User::factory()->create();
        $project = Project::factory()->ownedBy($duenio)->priority(ProjectPriority::Baja)->create();

        $this->actingAs($duenio)->patch(route('projects.priority', $project), [
            'priority' => ProjectPriority::Alta->value,
        ]);

        $log = Activity::where('action', ActivityAction::PrioridadCambiada->value)->firstOrFail();

        $this->assertSame('Baja → Alta', $log->detail);
        $this->assertSame($duenio->id, $log->user_id);
    }

    public function test_elegir_la_misma_prioridad_no_anota_nada(): void
    {
        $duenio  = User::factory()->create();
        $project = Project::factory()->ownedBy($duenio)->priority(ProjectPriority::Media)->create();

        $this->actingAs($duenio)->patch(route('projects.priority', $project), [
            'priority' => ProjectPriority::Media->value,
        ]);

        $this->assertSame(0, Activity::where('action', ActivityAction::PrioridadCambiada->value)->count());
    }

    public function test_el_control_aparece_solo_para_quien_puede(): void
    {
        $duenio  = User::factory()->create();
        $colab   = User::factory()->create();
        $project = Project::factory()->ownedBy($duenio)->create();
        $project->collaborators()->attach($colab);

        $this->actingAs($duenio)
            ->get(route('projects.show', $project))
            ->assertSee('prioridad-fila', escape: false);

        $this->actingAs($colab)
            ->get(route('projects.show', $project))
            ->assertDontSee('prioridad-fila', escape: false);
    }

    /** Al colaborador el formulario le muestra la prioridad, pero no editable. */
    public function test_el_formulario_le_muestra_la_prioridad_sin_poder_tocarla(): void
    {
        $colab   = User::factory()->create();
        $project = Project::factory()->priority(ProjectPriority::Alta)->create();
        $project->collaborators()->attach($colab);

        $this->actingAs($colab)
            ->get(route('projects.edit', $project))
            ->assertOk()
            ->assertSee('Alta')
            ->assertSee('La cambia el responsable del proyecto')
            ->assertDontSee('<select id="priority"', escape: false);
    }

    /** Un proyecto nuevo todavía no tiene responsable: la prioridad se elige. */
    public function test_al_crear_cualquiera_elige_la_prioridad(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('projects.create'))
            ->assertOk()
            ->assertSee('<select id="priority"', escape: false);
    }
}
