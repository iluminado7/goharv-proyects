<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regla acordada: edita quien esta metido en el proyecto (responsable o
 * colaborador) mas los responsables del panel. Archivar, solo responsable.
 */
class ProjectPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_responsable_del_proyecto_edita(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();

        $this->actingAs($user)->get(route('projects.edit', $project))->assertOk();
    }

    public function test_un_colaborador_edita(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->create();
        $project->collaborators()->attach($user);

        $this->actingAs($user)->get(route('projects.edit', $project))->assertOk();
    }

    public function test_un_miembro_ajeno_ve_pero_no_edita(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->create();

        $this->actingAs($user)->get(route('projects.show', $project))->assertOk();
        $this->actingAs($user)->get(route('projects.edit', $project))->assertForbidden();

        $this->actingAs($user)->put(route('projects.update', $project), [
            'name'     => 'Renombrado a la fuerza',
            'status'   => $project->status->value,
            'priority' => $project->priority->value,
        ])->assertForbidden();

        $this->assertNotSame('Renombrado a la fuerza', $project->fresh()->name);
    }

    public function test_un_miembro_ajeno_no_mueve_el_estado(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->status(ProjectStatus::Nuevo)->create();

        $this->actingAs($user)
            ->patch(route('projects.status', $project), ['status' => ProjectStatus::Terminado->value])
            ->assertForbidden();

        $this->assertSame(ProjectStatus::Nuevo, $project->fresh()->status);
    }

    public function test_el_responsable_del_panel_edita_cualquier_proyecto(): void
    {
        $admin   = User::factory()->admin()->create();
        $project = Project::factory()->create();

        $this->actingAs($admin)->get(route('projects.edit', $project))->assertOk();
        $this->actingAs($admin)->delete(route('projects.destroy', $project))->assertRedirect();
        $this->assertSoftDeleted($project);
    }

    public function test_un_colaborador_no_archiva(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->create();
        $project->collaborators()->attach($user);

        $this->actingAs($user)->delete(route('projects.destroy', $project))->assertForbidden();
        $this->assertNotSoftDeleted($project);
    }
}
