<?php

namespace Tests\Feature;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_formulario_de_alta_abre_con_filas_de_enlaces_vacias(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('projects.create'))
            ->assertOk()
            ->assertSee('Nuevo proyecto')
            ->assertSee('links[0][url]', escape: false);
    }

    public function test_se_crea_un_proyecto_con_sus_enlaces(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('projects.store'), [
                'name'        => 'Sitio nuevo',
                'description' => 'El rediseño completo.',
                'status'      => ProjectStatus::Nuevo->value,
                'priority'    => ProjectPriority::Alta->value,
                'owner_id'    => $user->id,
                'links'       => [
                    ['label' => 'Repo', 'url' => 'https://github.com/goharv/sitio'],
                    ['label' => '', 'url' => ''],
                    ['label' => 'Diseño', 'url' => 'https://figma.com/goharv'],
                ],
            ])
            ->assertRedirect();

        $project = Project::firstWhere('name', 'Sitio nuevo');

        $this->assertNotNull($project);
        $this->assertSame('sitio-nuevo', $project->slug);
        $this->assertSame(ProjectPriority::Alta, $project->priority);

        // Las filas vacias del formulario no se guardan y las posiciones quedan seguidas.
        $this->assertSame(['Repo', 'Diseño'], $project->links->pluck('label')->all());
        $this->assertSame([0, 1], $project->links->pluck('position')->all());
    }

    public function test_el_alta_queda_en_el_historial(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('projects.store'), [
            'name'     => 'Con historial',
            'status'   => ProjectStatus::Nuevo->value,
            'priority' => ProjectPriority::Media->value,
        ]);

        $update = Project::firstWhere('name', 'Con historial')->updates()->first();

        $this->assertSame('Proyecto creado.', $update->body);
        $this->assertSame($user->id, $update->user_id);
    }

    public function test_un_enlace_invalido_frena_el_alta(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('projects.store'), [
                'name'     => 'Con enlace roto',
                'status'   => ProjectStatus::Nuevo->value,
                'priority' => ProjectPriority::Media->value,
                'links'    => [['label' => 'Repo', 'url' => 'no-es-una-url']],
            ])
            ->assertSessionHasErrors('links.0.url');

        $this->assertDatabaseMissing('projects', ['name' => 'Con enlace roto']);
    }

    public function test_al_editar_se_reemplaza_la_lista_de_enlaces(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->status(ProjectStatus::Nuevo)->create();
        $project->syncLinks([
            ['label' => 'Viejo', 'url' => 'https://viejo.test'],
            ['label' => 'Otro', 'url' => 'https://otro.test'],
        ]);

        $this->actingAs($user)->put(route('projects.update', $project), [
            'name'     => $project->name,
            'status'   => $project->status->value,
            'priority' => $project->priority->value,
            'links'    => [['label' => 'Nuevo', 'url' => 'https://nuevo.test']],
        ])->assertRedirect();

        $this->assertSame(['Nuevo'], $project->fresh()->links->pluck('label')->all());
        $this->assertDatabaseCount('project_links', 1);
    }

    public function test_los_colaboradores_se_eligen_con_checkboxes(): void
    {
        $user  = User::factory()->create();
        $ana   = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);

        $project = Project::factory()->ownedBy($user)->create();
        $project->collaborators()->attach($ana);

        $this->actingAs($user)
            ->get(route('projects.edit', $project))
            ->assertOk()
            // Un checkbox por miembro, con el ya cargado tildado.
            ->assertSee('name="collaborators[]" value="'.$ana->id.'"', escape: false)
            ->assertSee('name="collaborators[]" value="'.$bruno->id.'"', escape: false)
            ->assertDontSee('<select id="collaborators"', escape: false);

        $this->actingAs($user)->put(route('projects.update', $project), [
            'name'          => $project->name,
            'status'        => $project->status->value,
            'priority'      => $project->priority->value,
            'collaborators' => [$ana->id, $bruno->id],
        ])->assertRedirect();

        $this->assertEqualsCanonicalizing(
            [$ana->id, $bruno->id],
            $project->fresh()->collaborators->pluck('id')->all()
        );
    }

    public function test_se_pueden_sacar_todos_los_colaboradores(): void
    {
        $user = User::factory()->create();
        $ana  = User::factory()->create();

        $project = Project::factory()->ownedBy($user)->create();
        $project->collaborators()->attach($ana);

        // Sin ninguno tildado el navegador no manda la clave.
        $this->actingAs($user)->put(route('projects.update', $project), [
            'name'     => $project->name,
            'status'   => $project->status->value,
            'priority' => $project->priority->value,
        ])->assertRedirect();

        $this->assertCount(0, $project->fresh()->collaborators);
    }

    public function test_un_colaborador_inexistente_frena_el_guardado(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();

        $this->actingAs($user)
            ->put(route('projects.update', $project), [
                'name'          => 'Renombrado',
                'status'        => $project->status->value,
                'priority'      => $project->priority->value,
                'collaborators' => [99999],
            ])
            ->assertSessionHasErrors('collaborators.0');

        $this->assertNotSame('Renombrado', $project->fresh()->name);
    }

    public function test_archivar_no_borra_ni_lo_deja_en_el_tablero(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();

        $this->actingAs($user)
            ->delete(route('projects.destroy', $project))
            ->assertRedirect(route('projects.index'));

        $this->assertSoftDeleted($project);
        $this->actingAs($user)->get(route('projects.index'))->assertDontSee($project->name);
    }

    public function test_el_tablero_pagina_de_a_treinta(): void
    {
        $user = User::factory()->create();
        Project::factory()->count(35)->create();

        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertViewHas('projects', fn ($projects) => $projects->count() === 30 && $projects->total() === 35);
    }

    public function test_el_orden_por_prioridad_no_depende_de_mysql(): void
    {
        $user = User::factory()->create();

        Project::factory()->priority(ProjectPriority::Baja)->create(['name' => 'Tercero']);
        Project::factory()->priority(ProjectPriority::Alta)->create(['name' => 'Primero']);
        Project::factory()->priority(ProjectPriority::Media)->create(['name' => 'Segundo']);

        $orden = Project::sorted('prioridad')->pluck('name')->all();

        $this->assertSame(['Primero', 'Segundo', 'Tercero'], $orden);
    }

    public function test_la_busqueda_encuentra_por_nombre_y_por_detalle(): void
    {
        Project::factory()->create(['name' => 'Catálogo mayorista', 'description' => 'Sin relación.']);
        Project::factory()->create(['name' => 'Otra cosa', 'description' => 'Incluye el catálogo viejo.']);
        Project::factory()->create(['name' => 'Nada que ver', 'description' => 'Tampoco.']);

        $this->assertCount(2, Project::filtered(['q' => 'catálogo'])->get());
    }
}
