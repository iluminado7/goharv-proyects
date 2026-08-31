<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchivedProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_los_archivados_tienen_su_propia_pantalla(): void
    {
        $user     = User::factory()->create();
        $enTablero = Project::factory()->create(['name' => 'Sigue vivo']);
        $archivado = Project::factory()->create(['name' => 'Ya no va']);
        $archivado->delete();

        $this->actingAs($user)
            ->get(route('projects.archived'))
            ->assertOk()
            ->assertSee('Ya no va')
            ->assertDontSee('Sigue vivo');
    }

    public function test_la_ruta_de_archivados_no_se_confunde_con_un_slug(): void
    {
        // Un proyecto llamado "Archivados" genera el slug 'archivados': la ruta
        // fija tiene que ganarle igual.
        $user = User::factory()->create();
        Project::factory()->create(['name' => 'Archivados', 'slug' => 'archivados']);

        $this->actingAs($user)
            ->get(route('projects.archived'))
            ->assertOk()
            ->assertSee('Salieron del tablero');
    }

    public function test_el_responsable_lo_restaura_y_vuelve_al_tablero(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();
        $project->delete();

        $this->actingAs($user)
            ->patch(route('projects.restore', $project))
            ->assertRedirect(route('projects.show', $project));

        $this->assertNotSoftDeleted($project);
        $this->actingAs($user)->get(route('projects.index'))->assertSee($project->name);
    }

    public function test_un_miembro_ajeno_no_restaura(): void
    {
        $ajeno   = User::factory()->create();
        $project = Project::factory()->create();
        $project->delete();

        $this->actingAs($ajeno)
            ->patch(route('projects.restore', $project))
            ->assertForbidden();

        $this->assertSoftDeleted($project);
    }

    public function test_el_responsable_del_panel_restaura_cualquiera(): void
    {
        $admin   = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $project->delete();

        $this->actingAs($admin)->patch(route('projects.restore', $project))->assertRedirect();
        $this->assertNotSoftDeleted($project);
    }

    public function test_archivar_y_restaurar_quedan_en_el_historial(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();

        $this->actingAs($user)->delete(route('projects.destroy', $project));
        $this->actingAs($user)->patch(route('projects.restore', $project));

        $movimientos = $project->fresh()->updates()->pluck('body')->all();

        $this->assertContains('Proyecto archivado.', $movimientos);
        $this->assertContains('Proyecto restaurado.', $movimientos);
    }

    public function test_el_tablero_avisa_cuantos_hay_archivados(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('projects.index'))->assertDontSee('Archivados');

        Project::factory()->create()->delete();
        Project::factory()->create()->delete();

        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertSee('Archivados')
            ->assertViewHas('archivados', 2);
    }
}
