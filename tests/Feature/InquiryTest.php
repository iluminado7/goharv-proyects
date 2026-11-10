<?php

namespace Tests\Feature;

use App\Enums\ActivityAction;
use App\Models\Activity;
use App\Models\CacciatoreMessage;
use App\Models\User;
use App\Notifications\ConsultaRecibida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** La pantalla Consultas web y los avisos que llevan a ella. */
class InquiryTest extends TestCase
{
    use RefreshDatabase;

    /** $at en hora argentina, que es como guarda el panel. */
    private function consulta(array $attrs = [], string $at = '2026-11-06 15:00:00'): CacciatoreMessage
    {
        $m = CacciatoreMessage::create(array_merge([
            'name'         => 'Ana',
            'last_name'    => 'Pérez',
            'company'      => 'Empresa SA',
            'email'        => 'ana@empresa.com.ar',
            'phone'        => '+5491145678901',
            'country'      => 'Argentina',
            'message'      => 'Necesitamos asesoramiento societario.',
            'page'         => '/',
            'locale'       => 'es',
            'consented_at' => now(),
        ], $attrs));
        $m->forceFill(['created_at' => Carbon::parse($at)])->save();

        return $m;
    }

    public function test_lista_las_consultas_con_sus_datos(): void
    {
        $this->consulta(['message' => "Primera línea\nSegunda línea", 'locale' => 'pt']);

        $this->actingAs(User::factory()->create())
            ->get(route('inquiries.index'))
            ->assertOk()
            ->assertSee('Ana Pérez')
            ->assertSee('Empresa SA')
            ->assertSee('ana@empresa.com.ar')
            ->assertSee("Primera línea\nSegunda línea")
            ->assertSee('title="Portugués">PT</span>', escape: false)
            ->assertSee('1 consulta');
    }

    public function test_las_mas_nuevas_primero(): void
    {
        $this->consulta(['name' => 'Vieja'], '2026-11-01 10:00:00');
        $this->consulta(['name' => 'Nueva'], '2026-11-08 10:00:00');

        $this->actingAs(User::factory()->create())
            ->get(route('inquiries.index'))
            ->assertSeeInOrder(['Nueva', 'Vieja']);
    }

    public function test_filtra_por_empresa(): void
    {
        $this->consulta(['name' => 'De Empresa', 'company' => 'Empresa SA']);
        $this->consulta(['name' => 'De Otra', 'company' => 'Otra SRL']);

        $this->actingAs(User::factory()->create())
            ->get(route('inquiries.index', ['empresa' => 'Otra SRL']))
            ->assertSee('De Otra')
            ->assertDontSee('De Empresa')
            ->assertSee('con estos filtros');
    }

    /** Una consulta de las 23 h es de ese dia: el panel guarda en hora argentina. */
    public function test_filtra_por_dia_en_hora_argentina(): void
    {
        $this->consulta(['name' => 'Tarde del 5'], '2026-11-05 23:30:00');
        $this->consulta(['name' => 'Mañana del 6'], '2026-11-06 00:30:00');

        $this->actingAs(User::factory()->create())
            ->get(route('inquiries.index', ['desde' => '2026-11-05', 'hasta' => '2026-11-05']))
            ->assertSee('Tarde del 5')
            ->assertDontSee('Mañana del 6');
    }

    public function test_sin_consultas_lo_dice(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('inquiries.index'))
            ->assertSee('Todavía no llegaron consultas desde el sitio.');
    }

    public function test_el_externo_no_entra_ni_la_ve_en_el_menu(): void
    {
        $externo = User::factory()->external()->create();

        $this->actingAs($externo)->get(route('inquiries.index'))->assertForbidden();
        $this->actingAs($externo)->get(route('projects.index'))->assertDontSee('>Consultas web</span>', escape: false);
    }

    public function test_sin_sesion_va_al_login(): void
    {
        $this->get(route('inquiries.index'))->assertRedirect(route('login'));
    }

    public function test_el_aviso_de_la_campana_lleva_a_consultas_web(): void
    {
        $user = User::factory()->create();
        $user->notify(new ConsultaRecibida($this->consulta()));

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertSee('Consulta web')
            ->assertSee('href="'.route('inquiries.index').'"', escape: false);
    }

    public function test_la_bitacora_filtra_lo_del_sitio_web(): void
    {
        Activity::anotar(ActivityAction::ConsultaRecibida, subject: 'Ana Pérez', detail: 'Empresa SA');
        Activity::anotar(ActivityAction::NotaNueva, subject: 'Un proyecto');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('activity.index', ['tipo' => 'sitio']))
            ->assertSee('Ana Pérez')
            ->assertDontSee('Un proyecto');
    }
}
