<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_se_deja_una_nota_sin_mover_el_proyecto(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->status(ProjectStatus::EnDesarrollo)->create();

        $this->actingAs($user)
            ->post(route('projects.comment', $project), ['body' => 'Falta que respondan del banco.'])
            ->assertRedirect();

        $update = $project->updates()->first();

        $this->assertSame('Falta que respondan del banco.', $update->body);
        $this->assertSame($user->id, $update->user_id);

        // Lo importante: el estado quedo donde estaba.
        $this->assertSame(ProjectStatus::EnDesarrollo, $project->fresh()->status);
        $this->assertFalse($update->isStatusChange());
    }

    /** La caja de comentarios vive en la ficha: del tablero hay que poder llegar. */
    public function test_el_tablero_tiene_como_llegar_a_la_ficha(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();

        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee(route('projects.show', $project), escape: false)
            ->assertSee('>Notas</a>', escape: false);
    }

    public function test_el_comentario_aparece_en_el_historial(): void
    {
        $user    = User::factory()->create(['name' => 'Ana']);
        $project = Project::factory()->ownedBy($user)->create();

        $this->actingAs($user)->post(route('projects.comment', $project), [
            'body' => 'Ojo con la fecha de entrega.',
        ]);

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Ojo con la fecha de entrega.')
            ->assertSee('Ana');
    }

    public function test_no_se_puede_mandar_vacio(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();

        $this->actingAs($user)
            ->post(route('projects.comment', $project), ['body' => '   '])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, $project->updates()->count());
    }

    public function test_hay_un_tope_de_mil_caracteres(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();

        $this->actingAs($user)
            ->post(route('projects.comment', $project), ['body' => str_repeat('a', 1001)])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, $project->updates()->count());
    }

    /** Decision tomada: comentar es mas abierto que editar. */
    public function test_cualquiera_del_equipo_comenta_aunque_no_participe(): void
    {
        $ajeno   = User::factory()->create();
        $project = Project::factory()->create();

        $this->actingAs($ajeno)
            ->post(route('projects.comment', $project), ['body' => 'Les dejo el contacto del proveedor.'])
            ->assertRedirect();

        $this->assertSame(1, $project->updates()->count());

        // Pero sigue sin poder editarlo ni moverlo.
        $this->actingAs($ajeno)->get(route('projects.edit', $project))->assertForbidden();
    }

    public function test_los_comentarios_no_se_mezclan_con_los_movimientos(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->status(ProjectStatus::Nuevo)->create();

        $project->moveTo(ProjectStatus::Inicio, $user);
        $this->actingAs($user)->post(route('projects.comment', $project), ['body' => 'Arrancamos con esto.']);

        $historial = $project->fresh()->updates()->get();

        $this->assertCount(2, $historial);
        $this->assertSame(1, $historial->filter->isStatusChange()->count());
        $this->assertSame(1, $historial->reject->isStatusChange()->count());
    }

    public function test_sin_sesion_no_se_comenta(): void
    {
        $project = Project::factory()->create();

        $this->post(route('projects.comment', $project), ['body' => 'Hola'])
            ->assertRedirect('/login');

        $this->assertSame(0, $project->updates()->count());
    }
}
