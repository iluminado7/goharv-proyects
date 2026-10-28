<?php

namespace Tests\Feature;

use App\Enums\ActivityAction;
use App\Models\Activity;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sacar del panel a un profesional externo cuando termina el trabajo. No borra
 * nada: la cuenta deja de entrar y desaparece de los desplegables, pero lo que
 * hizo queda donde esta.
 */
class MemberAccessTest extends TestCase
{
    use RefreshDatabase;

    // --- Quién puede -------------------------------------------------------

    public function test_un_socio_da_de_baja_a_un_externo(): void
    {
        $socio   = User::factory()->create();
        $externo = User::factory()->external()->create();

        $this->actingAs($socio)
            ->patch(route('members.access', $externo), ['is_active' => 0])
            ->assertRedirect();

        $this->assertFalse($externo->fresh()->is_active);
    }

    public function test_un_responsable_da_de_baja_a_cualquiera(): void
    {
        $admin = User::factory()->admin()->create();
        $socio = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('members.access', $socio), ['is_active' => 0])
            ->assertRedirect();

        $this->assertFalse($socio->fresh()->is_active);
    }

    /** Entre nosotros, sacar a alguien del equipo lo decide un responsable. */
    public function test_un_socio_no_da_de_baja_a_otro_socio(): void
    {
        $socio = User::factory()->create();
        $otro  = User::factory()->create();

        $this->actingAs($socio)
            ->patch(route('members.access', $otro), ['is_active' => 0])
            ->assertForbidden();

        $this->assertTrue($otro->fresh()->is_active);
    }

    public function test_un_socio_no_da_de_baja_a_un_responsable(): void
    {
        $socio = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($socio)
            ->patch(route('members.access', $admin), ['is_active' => 0])
            ->assertForbidden();
    }

    public function test_un_externo_no_entra_a_la_ruta(): void
    {
        $externo = User::factory()->external()->create();
        $otro    = User::factory()->external()->create();

        $this->actingAs($externo)
            ->patch(route('members.access', $otro), ['is_active' => 0])
            ->assertForbidden();
    }

    public function test_nadie_se_da_de_baja_a_si_mismo(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('members.access', $admin), ['is_active' => 0])
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->is_active);
    }

    /** Con dos responsables, uno puede dar de baja al otro: queda uno. */
    public function test_entre_dos_responsables_uno_puede_dar_de_baja_al_otro(): void
    {
        $admin = User::factory()->admin()->create();
        $otro  = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('members.access', $otro), ['is_active' => 0])
            ->assertRedirect();

        $this->assertFalse($otro->fresh()->is_active);
        $this->assertTrue($admin->fresh()->is_active);
    }

    // --- Qué pasa con el externo dado de baja ------------------------------

    public function test_pierde_el_acceso_en_el_acto(): void
    {
        $socio   = User::factory()->create();
        $externo = User::factory()->external()->create();

        $this->actingAs($socio)->patch(route('members.access', $externo), ['is_active' => 0]);

        $this->actingAs($externo->fresh())
            ->get(route('projects.index'))
            ->assertRedirect(route('login'));
    }

    /** No puede sumarse a proyectos nuevos: sale de todos los desplegables. */
    public function test_ya_no_se_lo_puede_elegir_para_un_proyecto(): void
    {
        $socio   = User::factory()->create();
        $externo = User::factory()->external()->create(['name' => 'Estudio Contable']);

        $opcion = 'name="collaborators[]" value="'.$externo->id.'"';

        $this->actingAs($socio)->get(route('projects.create'))->assertSee($opcion, escape: false);

        $this->actingAs($socio)->patch(route('members.access', $externo), ['is_active' => 0]);

        $this->actingAs($socio)
            ->get(route('projects.create'))
            ->assertDontSee($opcion, escape: false);
    }

    public function test_sale_de_la_lista_de_equipo_y_pasa_a_dados_de_baja(): void
    {
        $socio   = User::factory()->create();
        $externo = User::factory()->external()->create(['name' => 'Estudio Contable']);

        $this->actingAs($socio)->patch(route('members.access', $externo), ['is_active' => 0]);

        $this->actingAs($socio)
            ->get(route('members.index'))
            ->assertSee('Dados de baja')
            ->assertSee('Reactivar');
    }

    /** Lo que hizo no se toca: es justamente el punto de dar de baja. */
    public function test_sus_proyectos_notas_y_tareas_quedan(): void
    {
        $socio   = User::factory()->create();
        $externo = User::factory()->external()->create(['name' => 'Estudio Contable']);

        $project = Project::factory()->ownedBy($externo)->create(['name' => 'Balance 2026']);
        $project->comment($externo, 'Dejé el borrador en la carpeta');

        $this->actingAs($socio)->patch(route('members.access', $externo), ['is_active' => 0]);

        $this->assertDatabaseHas('users', ['id' => $externo->id, 'name' => 'Estudio Contable']);
        $this->assertSame($externo->id, $project->fresh()->owner_id);

        $this->actingAs($socio)
            ->get(route('projects.show', $project))
            ->assertSee('Estudio Contable')
            ->assertSee('Dejé el borrador en la carpeta');
    }

    public function test_queda_anotado_en_la_bitacora(): void
    {
        $socio   = User::factory()->create();
        $externo = User::factory()->external()->create(['name' => 'Estudio Contable']);

        $this->actingAs($socio)->patch(route('members.access', $externo), ['is_active' => 0]);

        $log = Activity::where('action', ActivityAction::CuentaDadaDeBaja->value)->firstOrFail();

        $this->assertSame($socio->id, $log->user_id);
        $this->assertSame('Estudio Contable', $log->subject);
    }

    // --- Volver atrás ------------------------------------------------------

    public function test_se_lo_puede_reactivar(): void
    {
        $socio   = User::factory()->create();
        $externo = User::factory()->external()->inactive()->create();

        $this->actingAs($socio)
            ->patch(route('members.access', $externo), ['is_active' => 1])
            ->assertRedirect();

        $this->assertTrue($externo->fresh()->is_active);

        $log = Activity::where('action', ActivityAction::CuentaReactivada->value)->firstOrFail();
        $this->assertSame($socio->id, $log->user_id);
    }

    /** Un socio solo ve en "dados de baja" a quienes puede reactivar. */
    public function test_al_socio_no_le_aparecen_las_bajas_del_equipo(): void
    {
        $socio = User::factory()->create();

        User::factory()->inactive()->create(['name' => 'Socio Que Se Fue']);
        User::factory()->external()->inactive()->create(['name' => 'Estudio Contable']);

        $this->actingAs($socio)
            ->get(route('members.index'))
            ->assertSee('Estudio Contable')
            ->assertDontSee('Socio Que Se Fue');
    }

    public function test_guardar_el_mismo_estado_no_ensucia_la_bitacora(): void
    {
        $socio   = User::factory()->create();
        $externo = User::factory()->external()->create();

        $this->actingAs($socio)->patch(route('members.access', $externo), ['is_active' => 1]);

        $this->assertSame(0, Activity::whereIn('action', [
            ActivityAction::CuentaDadaDeBaja->value,
            ActivityAction::CuentaReactivada->value,
        ])->count());
    }
}
