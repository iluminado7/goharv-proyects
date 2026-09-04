<?php

namespace Tests\Feature;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La consultora trabaja para varias empresas. La empresa es texto libre por
 * decisión del equipo, así que lo que hay que cuidar es que la misma empresa
 * no entre escrita de tres formas distintas.
 */
class ProjectClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_se_carga_un_proyecto_con_su_empresa(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('projects.store'), [
            'name'     => 'Portal de reclamos',
            'client'   => 'Cerrajería Leonardo',
            'status'   => ProjectStatus::Nuevo->value,
            'priority' => ProjectPriority::Media->value,
        ])->assertRedirect();

        $project = Project::firstWhere('name', 'Portal de reclamos');

        $this->assertSame('Cerrajería Leonardo', $project->client);
    }

    public function test_la_empresa_se_ve_en_el_tablero_y_en_la_ficha(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->forClient('RiseUp Compliance')->create();

        $this->actingAs($user)->get(route('projects.index'))->assertSee('RiseUp Compliance');
        $this->actingAs($user)->get(route('projects.show', $project))->assertSee('RiseUp Compliance');
    }

    public function test_el_tablero_filtra_por_empresa(): void
    {
        $user = User::factory()->create();

        Project::factory()->forClient('Cerrajería Leonardo')->create(['name' => 'Uno de Leonardo']);
        Project::factory()->forClient('Cerrajería Leonardo')->create(['name' => 'Otro de Leonardo']);
        Project::factory()->forClient('RiseUp Compliance')->create(['name' => 'De RiseUp']);
        Project::factory()->forClient(null)->create(['name' => 'Sin empresa']);

        $this->actingAs($user)
            ->get(route('projects.index', ['client' => 'Cerrajería Leonardo']))
            ->assertOk()
            ->assertSee('Uno de Leonardo')
            ->assertSee('Otro de Leonardo')
            ->assertDontSee('De RiseUp')
            ->assertDontSee('Sin empresa');
    }

    /** El filtro se arma con lo ya cargado: sin repetidos y alfabético. */
    public function test_la_lista_de_empresas_no_repite(): void
    {
        Project::factory()->forClient('Zeta SA')->create();
        Project::factory()->forClient('Alfa SRL')->create();
        Project::factory()->forClient('Alfa SRL')->create();
        Project::factory()->forClient(null)->create();

        $this->assertSame(['Alfa SRL', 'Zeta SA'], Project::clientes()->all());
    }

    /** Lo que evita que la misma empresa entre como dos. */
    public function test_los_espacios_de_mas_se_normalizan(): void
    {
        $project = Project::factory()->forClient("  Cerrajería   Leonardo \n")->create();

        $this->assertSame('Cerrajería Leonardo', $project->fresh()->client);
    }

    public function test_una_empresa_vacia_queda_en_nulo_y_no_ensucia_el_filtro(): void
    {
        $project = Project::factory()->forClient('   ')->create();

        $this->assertNull($project->fresh()->client);
        $this->assertCount(0, Project::clientes());
    }

    public function test_el_buscador_encuentra_por_empresa(): void
    {
        Project::factory()->forClient('RiseUp Compliance')->create(['name' => 'Nombre cualquiera', 'description' => 'Sin relación.']);
        Project::factory()->forClient('Otra Empresa')->create(['name' => 'Otro', 'description' => 'Tampoco.']);

        $encontrados = Project::filtered(['q' => 'RiseUp'])->pluck('name')->all();

        $this->assertSame(['Nombre cualquiera'], $encontrados);
    }

    public function test_el_formulario_sugiere_las_empresas_ya_cargadas(): void
    {
        Project::factory()->forClient('Cerrajería Leonardo')->create();

        $this->actingAs(User::factory()->create())
            ->get(route('projects.create'))
            ->assertOk()
            ->assertSee('<datalist id="empresas-cargadas">', escape: false)
            ->assertSee('<option value="Cerrajería Leonardo">', escape: false);
    }

    public function test_la_empresa_se_puede_cambiar_y_borrar(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->forClient('Vieja SA')->create();

        $this->actingAs($user)->put(route('projects.update', $project), [
            'name'     => $project->name,
            'client'   => 'Nueva SA',
            'status'   => $project->status->value,
            'priority' => $project->priority->value,
        ])->assertRedirect();

        $this->assertSame('Nueva SA', $project->fresh()->client);

        $this->actingAs($user)->put(route('projects.update', $project), [
            'name'     => $project->name,
            'client'   => '',
            'status'   => $project->status->value,
            'priority' => $project->priority->value,
        ])->assertRedirect();

        $this->assertNull($project->fresh()->client);
    }
}
