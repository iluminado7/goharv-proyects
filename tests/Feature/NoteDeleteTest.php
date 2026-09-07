<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_los_saltos_de_linea_se_guardan_y_se_muestran(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();

        $texto = "Diseños para la web:\nhttps://retool.com/\nhttps://carta.com/";

        $this->actingAs($user)
            ->post(route('projects.comment', $project), ['body' => $texto])
            ->assertRedirect();

        $this->assertSame($texto, $project->updates()->first()->body);

        $html = $this->actingAs($user)->get(route('projects.show', $project))->getContent();

        // El texto viaja con sus saltos y el CSS los respeta con pre-line, sin
        // necesidad de imprimir HTML sin escapar.
        $this->assertStringContainsString("Diseños para la web:\nhttps://retool.com/", $html);
        $this->assertStringContainsString('class="cuerpo"', $html);
    }

    public function test_el_autor_borra_su_propia_nota(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();
        $nota    = $project->comment($user, 'Me equivoqué al escribir esto.');

        $this->actingAs($user)
            ->delete(route('projects.comment.destroy', [$project, $nota]))
            ->assertRedirect();

        $this->assertDatabaseMissing('project_updates', ['id' => $nota->id]);
    }

    public function test_otro_miembro_no_borra_notas_ajenas(): void
    {
        $autor   = User::factory()->create();
        $ajeno   = User::factory()->create();
        $project = Project::factory()->create();
        $nota    = $project->comment($autor, 'Nota de otro.');

        $this->actingAs($ajeno)
            ->delete(route('projects.comment.destroy', [$project, $nota]))
            ->assertForbidden();

        $this->assertDatabaseHas('project_updates', ['id' => $nota->id]);
    }

    public function test_el_responsable_del_panel_borra_cualquier_nota(): void
    {
        $autor   = User::factory()->create();
        $admin   = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $nota    = $project->comment($autor, 'Algo fuera de lugar.');

        $this->actingAs($admin)
            ->delete(route('projects.comment.destroy', [$project, $nota]))
            ->assertRedirect();

        $this->assertDatabaseMissing('project_updates', ['id' => $nota->id]);
    }

    /** El historial de movimientos es la razón de ser del panel: no se borra. */
    public function test_un_cambio_de_estado_no_se_puede_borrar_ni_por_un_admin(): void
    {
        $admin   = User::factory()->admin()->create();
        $project = Project::factory()->status(ProjectStatus::Nuevo)->create();

        $project->moveTo(ProjectStatus::Inicio, $admin, 'Arrancamos.');
        $movimiento = $project->updates()->first();

        $this->actingAs($admin)
            ->delete(route('projects.comment.destroy', [$project, $movimiento]))
            ->assertForbidden();

        $this->assertDatabaseHas('project_updates', ['id' => $movimiento->id]);
    }

    public function test_no_se_borra_una_nota_de_otro_proyecto(): void
    {
        $user  = User::factory()->create();
        $otro  = Project::factory()->create();
        $nota  = $otro->comment($user, 'Nota del proyecto B.');
        $mio   = Project::factory()->ownedBy($user)->create();

        // La ruta esta anidada: la nota tiene que pertenecer al proyecto de la URL.
        $this->actingAs($user)
            ->delete(route('projects.comment.destroy', [$mio, $nota]))
            ->assertNotFound();

        $this->assertDatabaseHas('project_updates', ['id' => $nota->id]);
    }

    public function test_el_boton_solo_aparece_en_las_notas_borrables(): void
    {
        $autor   = User::factory()->create();
        $ajeno   = User::factory()->create();
        $project = Project::factory()->status(ProjectStatus::Nuevo)->create();

        $project->comment($autor, 'Mi nota.');
        $project->moveTo(ProjectStatus::Inicio, $autor, 'Un movimiento.');

        // El autor ve un solo botón: el de su nota, no el del movimiento.
        $html = $this->actingAs($autor)->get(route('projects.show', $project))->getContent();
        $this->assertSame(1, substr_count($html, 'borrar-nota'));

        $html = $this->actingAs($ajeno)->get(route('projects.show', $project))->getContent();
        $this->assertStringNotContainsString('borrar-nota', $html);
    }
}
