<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El profesional externo no es del equipo: entra a trabajar en los proyectos
 * que le delegan. Adentro de ellos puede casi todo lo que haria un socio; lo
 * que no puede es ver el resto del panel ni mover gente.
 */
class ExternalRoleTest extends TestCase
{
    use RefreshDatabase;

    // --- Lo que ve ---------------------------------------------------------

    public function test_el_tablero_le_muestra_solo_sus_proyectos(): void
    {
        $externo = User::factory()->external()->create();

        Project::factory()->ownedBy($externo)->create(['name' => 'El que le dieron']);
        Project::factory()->create(['name' => 'Uno de adentro']);

        $colabora = Project::factory()->create(['name' => 'Donde colabora']);
        $colabora->collaborators()->attach($externo);

        $this->actingAs($externo)
            ->get(route('projects.index'))
            ->assertSee('El que le dieron')
            ->assertSee('Donde colabora')
            ->assertDontSee('Uno de adentro');
    }

    public function test_no_entra_a_la_ficha_de_un_proyecto_ajeno(): void
    {
        $externo = User::factory()->external()->create();
        $ajeno   = Project::factory()->create();

        $this->actingAs($externo)
            ->get(route('projects.show', $ajeno))
            ->assertForbidden();
    }

    /** Los contadores del tablero cuentan lo mismo que la lista. */
    public function test_los_contadores_no_delatan_proyectos_ajenos(): void
    {
        $externo = User::factory()->external()->create();

        Project::factory()->ownedBy($externo)->create();
        Project::factory()->count(4)->create();

        $this->actingAs($externo)
            ->get(route('projects.index'))
            ->assertViewHas('total', 1);
    }

    public function test_el_filtro_de_empresas_solo_trae_las_suyas(): void
    {
        $externo = User::factory()->external()->create();

        Project::factory()->ownedBy($externo)->forClient('La suya')->create();
        Project::factory()->forClient('Ajena SRL')->create();

        $this->assertSame(['La suya'], Project::clientes($externo)->all());
    }

    public function test_los_archivados_tambien_estan_filtrados(): void
    {
        $externo = User::factory()->external()->create();

        $suyo = Project::factory()->ownedBy($externo)->create(['name' => 'Suyo archivado']);
        $otro = Project::factory()->create(['name' => 'Ajeno archivado']);
        $suyo->delete();
        $otro->delete();

        $this->actingAs($externo)
            ->get(route('projects.archived'))
            ->assertSee('Suyo archivado')
            ->assertDontSee('Ajeno archivado');
    }

    // --- Equipo y actividad ------------------------------------------------

    public function test_no_entra_a_equipo(): void
    {
        $this->actingAs(User::factory()->external()->create())
            ->get(route('members.index'))
            ->assertForbidden();
    }

    public function test_no_entra_a_la_actividad(): void
    {
        $this->actingAs(User::factory()->external()->create())
            ->get(route('activity.index'))
            ->assertForbidden();
    }

    public function test_el_menu_no_le_ofrece_equipo(): void
    {
        $this->actingAs(User::factory()->external()->create())
            ->get(route('projects.index'))
            ->assertDontSee('>Equipo</span>', escape: false)
            ->assertSee('>Proyectos</span>', escape: false);
    }

    // --- Lo que puede hacer adentro ----------------------------------------

    public function test_gestiona_el_proyecto_que_le_delegaron(): void
    {
        $externo = User::factory()->external()->create();
        $project = Project::factory()->ownedBy($externo)->create();

        $this->actingAs($externo)
            ->get(route('projects.show', $project))
            ->assertOk();

        $this->actingAs($externo)
            ->patch(route('projects.status', $project), ['status' => 'en_desarrollo'])
            ->assertSessionHasNoErrors();

        $this->assertSame('en_desarrollo', $project->fresh()->status->value);

        $this->actingAs($externo)
            ->post(route('projects.comment', $project), ['body' => 'Arranque con esto'])
            ->assertSessionHasNoErrors();

        $this->actingAs($externo)
            ->post(route('tasks.store', $project), ['title' => 'Mandar el presupuesto'])
            ->assertRedirect();

        $this->assertSame('Mandar el presupuesto', $project->tasks()->first()->title);
    }

    // --- Lo que no puede hacer ---------------------------------------------

    public function test_no_crea_proyectos(): void
    {
        $externo = User::factory()->external()->create();

        $this->actingAs($externo)->get(route('projects.create'))->assertForbidden();

        $this->actingAs($externo)
            ->post(route('projects.store'), [
                'name' => 'Inventado', 'status' => 'nuevo', 'priority' => 'media',
            ])
            ->assertForbidden();

        $this->assertSame(0, Project::count());
    }

    public function test_no_le_pasa_el_proyecto_a_otro(): void
    {
        $externo = User::factory()->external()->create();
        $otro    = User::factory()->create();
        $project = Project::factory()->ownedBy($externo)->create();

        $this->actingAs($externo)->put(route('projects.update', $project), [
            'name'     => $project->name,
            'status'   => $project->status->value,
            'priority' => $project->priority->value,
            'owner_id' => $otro->id,
        ])->assertRedirect();

        $this->assertSame($externo->id, $project->fresh()->owner_id);
    }

    public function test_no_suma_colaboradores(): void
    {
        $externo = User::factory()->external()->create();
        $otro    = User::factory()->create();
        $project = Project::factory()->ownedBy($externo)->create();

        $this->actingAs($externo)->put(route('projects.update', $project), [
            'name'          => $project->name,
            'status'        => $project->status->value,
            'priority'      => $project->priority->value,
            'collaborators' => [$otro->id],
        ])->assertRedirect();

        $this->assertCount(0, $project->fresh()->collaborators);
    }

    /** Guardar sin tocar nada tampoco puede vaciar la lista que puso el equipo. */
    public function test_guardar_no_borra_los_colaboradores_que_puso_el_equipo(): void
    {
        $externo = User::factory()->external()->create();
        $socio   = User::factory()->create();
        $project = Project::factory()->ownedBy($externo)->create();
        $project->collaborators()->attach($socio);

        $this->actingAs($externo)->put(route('projects.update', $project), [
            'name'     => 'Nombre nuevo',
            'status'   => $project->status->value,
            'priority' => $project->priority->value,
        ])->assertRedirect();

        $this->assertSame('Nombre nuevo', $project->fresh()->name);
        $this->assertCount(1, $project->fresh()->collaborators);
    }

    public function test_el_formulario_no_le_muestra_el_equipo(): void
    {
        $externo = User::factory()->external()->create();
        $project = Project::factory()->ownedBy($externo)->create();

        User::factory()->create(['name' => 'Alguien De Adentro']);

        $this->actingAs($externo)
            ->get(route('projects.edit', $project))
            ->assertOk()
            ->assertDontSee('Alguien De Adentro')
            ->assertDontSee('name="collaborators[]"', escape: false);
    }

    // --- El rol se puede asignar -------------------------------------------

    public function test_un_responsable_puede_crear_una_cuenta_externa(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('members.store'), [
                'name'     => 'Estudio Contable',
                'email'    => 'estudio@ejemplo.com',
                'password' => 'una-clave-larga-y-rara',
                'role'     => 'external',
            ])
            ->assertRedirect();

        $this->assertTrue(User::where('email', 'estudio@ejemplo.com')->first()->isExternal());
    }
}
