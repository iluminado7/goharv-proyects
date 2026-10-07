<?php

namespace Tests\Feature;

use App\Enums\ActivityAction;
use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Models\Activity;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private function ultima(): Activity
    {
        return Activity::latest('id')->firstOrFail();
    }

    // --- Acceso -----------------------------------------------------------

    public function test_queda_registrado_quien_entra(): void
    {
        $user = User::factory()->create(['password' => 'clave-larga']);

        $this->post('/login', ['email' => $user->email, 'password' => 'clave-larga']);

        $log = $this->ultima();

        $this->assertSame(ActivityAction::Ingreso, $log->action);
        $this->assertSame($user->id, $log->user_id);
        $this->assertNotNull($log->ip);
    }

    public function test_la_clave_equivocada_se_distingue_del_correo_inexistente(): void
    {
        $user = User::factory()->create(['password' => 'clave-larga']);

        $this->post('/login', ['email' => $user->email, 'password' => 'la-que-no-es']);
        $this->assertSame(ActivityAction::ClaveIncorrecta, $this->ultima()->action);
        $this->assertSame($user->id, $this->ultima()->user_id, 'Se sabe de quién era la cuenta');

        $this->post('/login', ['email' => 'nadie@ejemplo.test', 'password' => 'cualquiera']);

        $log = $this->ultima();
        $this->assertSame(ActivityAction::CorreoInexistente, $log->action);
        $this->assertNull($log->user_id);
        $this->assertSame('nadie@ejemplo.test', $log->subject, 'Hay que ver qué correo se intentó');
    }

    public function test_tambien_se_anota_el_intento_con_una_cuenta_de_baja(): void
    {
        $baja = User::factory()->inactive()->create(['password' => 'clave-larga']);

        $this->post('/login', ['email' => $baja->email, 'password' => 'clave-larga']);

        $this->assertSame(ActivityAction::CuentaDeBaja, $this->ultima()->action);
    }

    public function test_la_salida_tambien_queda(): void
    {
        $this->actingAs(User::factory()->create())->post('/logout');

        $this->assertSame(ActivityAction::Salida, $this->ultima()->action);
    }

    // --- Proyectos --------------------------------------------------------

    public function test_el_alta_de_un_proyecto(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('projects.store'), [
            'name'     => 'Portal nuevo',
            'status'   => ProjectStatus::Nuevo->value,
            'priority' => ProjectPriority::Media->value,
        ]);

        $log = $this->ultima();

        $this->assertSame(ActivityAction::ProyectoCreado, $log->action);
        $this->assertSame('Portal nuevo', $log->subject);
        $this->assertSame($user->id, $log->user_id);
    }

    public function test_el_cambio_de_estado_dice_de_donde_a_donde(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->status(ProjectStatus::Nuevo)->create();

        $this->actingAs($user)->patch(route('projects.status', $project), [
            'status' => ProjectStatus::EnDesarrollo->value,
        ]);

        $log = $this->ultima();

        $this->assertSame(ActivityAction::EstadoCambiado, $log->action);
        $this->assertSame('Nuevo → En desarrollo', $log->detail);
    }

    /** moveTo() también se usa para dejar notas sobre el estado actual. */
    public function test_una_nota_sobre_el_mismo_estado_no_cuenta_como_cambio(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->status(ProjectStatus::Inicio)->create();

        $this->actingAs($user)->patch(route('projects.status', $project), [
            'status' => ProjectStatus::Inicio->value,
            'note'   => 'Seguimos esperando.',
        ]);

        $this->assertSame(0, Activity::where('action', ActivityAction::EstadoCambiado->value)->count());
    }

    public function test_el_cambio_de_prioridad(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->priority(ProjectPriority::Baja)->create();

        $this->actingAs($user)->put(route('projects.update', $project), [
            'name'     => $project->name,
            'status'   => $project->status->value,
            'priority' => ProjectPriority::Alta->value,
        ]);

        $log = Activity::where('action', ActivityAction::PrioridadCambiada->value)->firstOrFail();

        $this->assertSame('Baja → Alta', $log->detail);
    }

    public function test_guardar_sin_tocar_la_prioridad_no_anota_nada(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->priority(ProjectPriority::Media)->create();

        $this->actingAs($user)->put(route('projects.update', $project), [
            'name'     => 'Otro nombre',
            'status'   => $project->status->value,
            'priority' => ProjectPriority::Media->value,
        ]);

        $this->assertSame(0, Activity::where('action', ActivityAction::PrioridadCambiada->value)->count());
    }

    public function test_la_nota_nueva(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->create();

        $this->actingAs($user)->post(route('projects.comment', $project), [
            'body' => 'Falta que respondan del banco.',
        ]);

        $log = $this->ultima();

        $this->assertSame(ActivityAction::NotaNueva, $log->action);
        $this->assertStringContainsString('banco', $log->detail);
    }

    public function test_archivar_y_restaurar(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();

        $this->actingAs($user)->delete(route('projects.destroy', $project));
        $this->assertSame(ActivityAction::ProyectoArchivado, $this->ultima()->action);

        $this->actingAs($user)->patch(route('projects.restore', $project));
        $this->assertSame(ActivityAction::ProyectoRestaurado, $this->ultima()->action);
    }

    /** El registro más importante: tiene que sobrevivir al borrado. */
    public function test_el_borrado_definitivo_queda_aunque_el_proyecto_ya_no_exista(): void
    {
        $admin   = User::factory()->admin()->create();
        $project = Project::factory()->create(['name' => 'Proyecto que se fue']);
        $project->delete();

        $this->actingAs($admin)->delete(route('projects.force-destroy', $project), [
            'confirmacion' => 'Proyecto que se fue',
        ]);

        $log = Activity::where('action', ActivityAction::ProyectoEliminado->value)->firstOrFail();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->assertNull($log->project_id, 'La clave foránea se vacía, no arrastra el registro');
        $this->assertSame('Proyecto que se fue', $log->subject, 'El nombre queda congelado en el log');
        $this->assertSame($admin->id, $log->user_id);
    }

    // --- La pantalla ------------------------------------------------------

    public function test_solo_los_responsables_del_panel_ven_la_actividad(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('activity.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('activity.index'))
            ->assertOk();
    }

    public function test_la_pantalla_filtra_por_tipo(): void
    {
        $admin   = User::factory()->admin()->create();
        $project = Project::factory()->create(['name' => 'Proyecto visible']);

        Activity::anotar(ActivityAction::CorreoInexistente, subject: 'intruso@ejemplo.test');
        Activity::anotar(ActivityAction::ProyectoCreado, $admin, $project);

        $this->actingAs($admin)
            ->get(route('activity.index', ['tipo' => 'fallos']))
            ->assertOk()
            ->assertSee('intruso@ejemplo.test')
            ->assertDontSee('Proyecto visible');

        $this->actingAs($admin)
            ->get(route('activity.index', ['tipo' => 'proyectos']))
            ->assertSee('Proyecto visible')
            ->assertDontSee('intruso@ejemplo.test');
    }

    /** Si la bitácora falla, la acción que la originó tiene que seguir. */
    public function test_un_log_roto_no_voltea_la_operacion(): void
    {
        $user = User::factory()->create();

        \Illuminate\Support\Facades\Schema::drop('activity_logs');

        $this->actingAs($user)->post(route('projects.store'), [
            'name'     => 'Se guarda igual',
            'status'   => ProjectStatus::Nuevo->value,
            'priority' => ProjectPriority::Media->value,
        ])->assertRedirect();

        $this->assertDatabaseHas('projects', ['name' => 'Se guarda igual']);
    }
}
