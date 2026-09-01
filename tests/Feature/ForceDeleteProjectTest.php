<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Es la unica accion del panel que no se puede deshacer. Los frenos son tres:
 * solo responsables del panel, solo sobre proyectos ya archivados, y hay que
 * escribir el nombre del proyecto.
 */
class ForceDeleteProjectTest extends TestCase
{
    use RefreshDatabase;

    private function archivado(): Project
    {
        $project = Project::factory()->create(['name' => 'Proyecto viejo']);
        $project->syncLinks([['label' => 'Repo', 'url' => 'https://ejemplo.test/repo']]);
        $project->collaborators()->attach(User::factory()->create());
        $project->comment(User::factory()->create(), 'Una nota cualquiera.');
        $project->delete();

        return $project;
    }

    public function test_el_responsable_del_panel_lo_borra_escribiendo_el_nombre(): void
    {
        $admin   = User::factory()->admin()->create();
        $project = $this->archivado();

        $this->actingAs($admin)
            ->delete(route('projects.force-destroy', $project), ['confirmacion' => 'Proyecto viejo'])
            ->assertRedirect(route('projects.archived'));

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);

        // En cascada se va todo lo colgado del proyecto.
        $this->assertDatabaseMissing('project_updates', ['project_id' => $project->id]);
        $this->assertDatabaseMissing('project_links', ['project_id' => $project->id]);
        $this->assertDatabaseMissing('project_user', ['project_id' => $project->id]);
    }

    public function test_con_el_nombre_mal_escrito_no_borra_nada(): void
    {
        $admin   = User::factory()->admin()->create();
        $project = $this->archivado();

        $this->actingAs($admin)
            ->from(route('projects.delete.confirm', $project))
            ->delete(route('projects.force-destroy', $project), ['confirmacion' => 'proyecto viejo'])
            ->assertSessionHasErrors('confirmacion');

        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    public function test_sin_confirmacion_tampoco(): void
    {
        $admin   = User::factory()->admin()->create();
        $project = $this->archivado();

        $this->actingAs($admin)
            ->delete(route('projects.force-destroy', $project), [])
            ->assertSessionHasErrors('confirmacion');

        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    public function test_un_miembro_no_puede_borrar_ni_el_suyo(): void
    {
        $duenio = User::factory()->create();

        $project = Project::factory()->ownedBy($duenio)->create(['name' => 'Mio']);
        $project->delete();

        $this->actingAs($duenio)
            ->delete(route('projects.force-destroy', $project), ['confirmacion' => 'Mio'])
            ->assertForbidden();

        $this->actingAs($duenio)
            ->get(route('projects.delete.confirm', $project))
            ->assertForbidden();

        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    /** Hay que archivar primero: obliga a pasar por un paso reversible. */
    public function test_un_proyecto_activo_no_se_puede_borrar(): void
    {
        $admin   = User::factory()->admin()->create();
        $project = Project::factory()->create(['name' => 'En curso']);

        $this->actingAs($admin)
            ->delete(route('projects.force-destroy', $project), ['confirmacion' => 'En curso'])
            ->assertNotFound();

        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    public function test_la_pantalla_avisa_que_se_pierde(): void
    {
        $admin   = User::factory()->admin()->create();
        $project = $this->archivado();

        $this->actingAs($admin)
            ->get(route('projects.delete.confirm', $project))
            ->assertOk()
            ->assertSee('Borrar para siempre')
            ->assertSee('Proyecto viejo')
            ->assertSee('movimiento(s) y comentario(s)');
    }

    public function test_el_boton_solo_lo_ve_el_responsable_del_panel(): void
    {
        $project = $this->archivado();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('projects.archived'))
            ->assertSee('>Borrar</a>', escape: false);

        $this->actingAs(User::factory()->create())
            ->get(route('projects.archived'))
            ->assertDontSee('>Borrar</a>', escape: false);
    }
}
