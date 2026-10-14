<?php

namespace Tests\Feature;

use App\Enums\ActivityAction;
use App\Enums\NotificationType;
use App\Models\Activity;
use App\Models\ConsultingRequest;
use App\Models\ContactMessage;
use App\Models\InCompanyRequest;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Una consulta del sitio es el caso donde enterarse tarde cuesta plata, y no
 * tiene responsable asignado: le llega a todo el equipo activo.
 */
class InquiryNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function contacto(array $extra = []): array
    {
        return array_merge([
            'name'    => 'Lucía Fernández',
            'email'   => 'lucia@empresa.test',
            'phone'   => '1133445566',
            'company' => 'Empresa Test SA',
            'message' => 'Quisiera una reunión.',
            'consent' => 'on',
        ], $extra);
    }

    public function test_una_consulta_de_contacto_avisa_a_todo_el_equipo(): void
    {
        $uno = User::factory()->create();
        $dos = User::factory()->admin()->create();

        $this->postJson(route('api.contact.store'), $this->contacto())->assertCreated();

        foreach ([$uno, $dos] as $quien) {
            $aviso = $quien->fresh()->notifications->first();

            $this->assertNotNull($aviso, 'Le tiene que llegar a todos');
            $this->assertSame(NotificationType::ConsultaWeb->value, $aviso->data['tipo']);
            $this->assertSame('Contacto', $aviso->data['proyecto']);
            $this->assertSame('Lucía Fernández', $aviso->data['actor']);
            $this->assertSame('Empresa Test SA', $aviso->data['detalle']);
        }
    }

    public function test_el_aviso_lleva_al_listado_filtrado(): void
    {
        $user = User::factory()->create();

        $this->postJson(route('api.contact.store'), $this->contacto());

        $url = $user->fresh()->notifications->first()->data['url'];

        $this->assertStringContainsString('consultas', $url);
        $this->assertStringContainsString('form=contacto', $url);

        // Y el enlace aparece en la campana, no solo en el payload.
        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Consulta del sitio')
            ->assertSee('form=contacto', escape: false);
    }

    public function test_queda_en_la_bitacora_con_su_formulario(): void
    {
        User::factory()->create();

        $this->postJson(route('api.contact.store'), $this->contacto());

        $log = Activity::where('action', ActivityAction::ConsultaRecibida->value)->firstOrFail();

        $this->assertSame('Lucía Fernández', $log->subject);
        $this->assertSame('Contacto', $log->detail);
        // La manda alguien de afuera: no hay usuario del panel detrás.
        $this->assertNull($log->user_id);
        $this->assertNotNull($log->ip);
    }

    public function test_los_cuatro_formularios_avisan_con_su_nombre(): void
    {
        $user = User::factory()->create();

        $base = ['phone' => '11223344', 'consented_at' => now()];

        ContactMessage::create([...$base, 'name' => 'A', 'email' => 'a@t.test', 'message' => 'Hola']);

        InCompanyRequest::create([...$base, 'name' => 'B', 'email' => 'b@t.test',
            'role' => 'RRHH', 'company' => 'B SA', 'team_size' => '10-20']);

        ConsultingRequest::create([...$base, 'name' => 'C', 'email' => 'c@t.test',
            'role' => 'Dueño', 'company' => 'C SA', 'challenge' => 'costos', 'goal' => 'Bajar costos']);

        Lead::create([...$base, 'name' => 'D', 'email' => 'd@t.test',
            'program' => 'programa-ia', 'source' => 'home']);

        $etiquetas = $user->fresh()->notifications->pluck('data.proyecto')->sort()->values()->all();

        $this->assertSame([
            'Avisame: Inteligencia Artificial para Empresas',
            'Contacto',
            'Formación In Company',
            'Quiero analizar mi empresa',
        ], $etiquetas);
    }

    public function test_las_cuentas_de_baja_no_reciben_consultas(): void
    {
        $activo = User::factory()->create();
        $baja   = User::factory()->inactive()->create();

        $this->postJson(route('api.contact.store'), $this->contacto());

        $this->assertCount(1, $activo->fresh()->notifications);
        $this->assertCount(0, $baja->fresh()->notifications);
    }

    /** Del otro lado hay alguien esperando un "¡Gracias!". */
    public function test_si_el_aviso_falla_la_consulta_se_guarda_igual(): void
    {
        User::factory()->create();

        \Illuminate\Support\Facades\Schema::drop('notifications');

        $this->postJson(route('api.contact.store'), $this->contacto())->assertCreated();

        $this->assertDatabaseHas('contact_messages', ['email' => 'lucia@empresa.test']);
    }

    public function test_el_campo_trampa_no_dispara_avisos(): void
    {
        $user = User::factory()->create();

        $this->postJson(route('api.contact.store'), $this->contacto(['website' => 'soy-un-bot']))
            ->assertCreated();

        $this->assertCount(0, $user->fresh()->notifications);
        $this->assertSame(0, Activity::count());
    }

    public function test_la_actividad_se_filtra_por_sitio(): void
    {
        $admin = User::factory()->admin()->create();

        $this->postJson(route('api.contact.store'), $this->contacto());

        $this->actingAs($admin)
            ->get(route('activity.index', ['tipo' => 'sitio']))
            ->assertOk()
            ->assertSee('Lucía Fernández')
            ->assertDontSee('Ingreso</strong>', escape: false);
    }
}
