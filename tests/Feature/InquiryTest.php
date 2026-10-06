<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InquiryTest extends TestCase
{
    use RefreshDatabase;

    /** $at en UTC, que es como se guarda. */
    private function consulta(array $attrs = [], string $at = '2026-10-06 15:00:00'): ContactMessage
    {
        $m = ContactMessage::create(array_merge([
            'name'         => 'Ana Pérez',
            'email'        => 'ana@empresa.com.ar',
            'phone'        => '1145678901',
            'company'      => 'Empresa SA',
            'country'      => 'Argentina',
            'unit'         => 'Business',
            'message'      => 'Quiero franquiciar mi negocio.',
            'page'         => '/contacto',
            'consented_at' => now(),
        ], $attrs));
        $m->forceFill(['created_at' => Carbon::parse($at, 'UTC')])->save();

        return $m;
    }

    private function pedido(string $program, array $attrs = [], string $at = '2026-10-06 15:00:00'): Lead
    {
        $l = Lead::create(array_merge([
            'name'         => 'Juan Gómez',
            'email'        => 'juan@consorcio.com.ar',
            'phone'        => '2615551234',
            'newsletter'   => true,
            'program'      => $program,
            'source'       => 'academy',
            'page'         => '/academy',
            'consented_at' => now(),
        ], $attrs));
        $l->forceFill(['created_at' => Carbon::parse($at, 'UTC')])->save();

        return $l;
    }

    public function test_sin_sesion_va_al_login(): void
    {
        $this->get(route('inquiries.index'))->assertRedirect(route('login'));
    }

    public function test_arranca_en_contacto_y_no_mezcla_los_avisame(): void
    {
        $this->consulta(['name' => 'Ana Contacto', 'message' => "Primera línea\nSegunda línea"]);
        $this->pedido('workshop-consorcios', ['name' => 'Juan Avisame']);

        $this->actingAs(User::factory()->create())
            ->get(route('inquiries.index'))
            ->assertOk()
            ->assertSee('Ana Contacto')
            ->assertSee('Empresa SA')
            ->assertSee("Primera línea\nSegunda línea")
            ->assertDontSee('Juan Avisame');
    }

    public function test_cada_programa_tiene_su_listado_con_su_titulo(): void
    {
        $this->consulta(['name' => 'Ana Contacto']);
        $this->pedido('workshop-consorcios', ['name' => 'Juan Consorcios']);
        $this->pedido('workshop-access-control', ['name' => 'Eva Accesos']);

        $this->actingAs(User::factory()->create())
            ->get(route('inquiries.index', ['form' => 'workshop-consorcios']))
            ->assertOk()
            ->assertSee('<h1 class="page">Consorcios de propiedad horizontal</h1>', escape: false)
            ->assertSee('Juan Consorcios')
            ->assertDontSee('Eva Accesos')
            ->assertDontSee('Ana Contacto');
    }

    /** Los dos workshops del sitio aparecen aunque todavia nadie haya pedido aviso. */
    public function test_los_contadores_muestran_todos_los_formularios(): void
    {
        $this->consulta();
        $this->consulta();
        $this->pedido('workshop-consorcios');

        $this->actingAs(User::factory()->create())
            ->get(route('inquiries.index'))
            ->assertSeeInOrder(['2', 'contacto'])
            ->assertSeeInOrder(['1', 'avisame · Consorcios de propiedad horizontal'])
            ->assertSeeInOrder(['0', 'avisame · Business Workshop: Access control and smart locks']);
    }

    /** Un programa nuevo del sitio aparece igual, con su etiqueta pasada en limpio. */
    public function test_un_programa_que_el_panel_no_conoce_aparece_igual(): void
    {
        $this->pedido('workshop-nuevo', ['name' => 'Lu Nuevo']);

        $this->actingAs(User::factory()->create())
            ->get(route('inquiries.index', ['form' => 'workshop-nuevo']))
            ->assertOk()
            ->assertSee('Workshop Nuevo')
            ->assertSee('Lu Nuevo');
    }

    public function test_un_formulario_inexistente_da_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('inquiries.index', ['form' => 'no-existe']))
            ->assertNotFound();
    }

    public function test_filtra_por_empresa_en_contacto(): void
    {
        $this->consulta(['name' => 'De Acme', 'company' => 'Acme']);
        $this->consulta(['name' => 'De Otra', 'company' => 'Otra SRL']);

        $this->actingAs(User::factory()->create())
            ->get(route('inquiries.index', ['empresa' => 'Acme']))
            ->assertSee('De Acme')
            ->assertDontSee('De Otra');
    }

    public function test_filtra_por_unidad_en_contacto(): void
    {
        $this->consulta(['name' => 'Quiere Business', 'unit' => 'Business']);
        $this->consulta(['name' => 'Quiere Academy', 'unit' => 'Academy']);
        $this->consulta(['name' => 'Sin unidad', 'unit' => null]);

        $this->actingAs(User::factory()->create())
            ->get(route('inquiries.index', ['unidad' => 'Academy']))
            ->assertSee('Todas las unidades')
            ->assertSee('Quiere Academy')
            ->assertDontSee('Quiere Business')
            ->assertDontSee('Sin unidad');
    }

    /** Empresa y unidad se combinan entre si y con las fechas. */
    public function test_los_filtros_se_combinan(): void
    {
        $this->consulta(['name' => 'Acme Academy', 'company' => 'Acme', 'unit' => 'Academy']);
        $this->consulta(['name' => 'Acme Business', 'company' => 'Acme', 'unit' => 'Business']);
        $this->consulta(['name' => 'Otra Academy', 'company' => 'Otra SRL', 'unit' => 'Academy']);

        $this->actingAs(User::factory()->create())
            ->get(route('inquiries.index', ['empresa' => 'Acme', 'unidad' => 'Academy']))
            ->assertSee('Acme Academy')
            ->assertDontSee('Acme Business')
            ->assertDontSee('Otra Academy');
    }

    /** "Avisame" no pide empresa ni unidad: los filtros no aparecen y, si llegan en la URL, no se aplican. */
    public function test_en_avisame_no_hay_filtro_de_empresa_ni_de_unidad(): void
    {
        $this->consulta(['company' => 'Acme']);
        $this->pedido('workshop-consorcios', ['name' => 'Juan Consorcios']);

        $this->actingAs(User::factory()->create())
            ->get(route('inquiries.index', ['form' => 'workshop-consorcios', 'empresa' => 'Acme', 'unidad' => 'Academy']))
            ->assertSee('Juan Consorcios')
            ->assertDontSee('Todas las empresas')
            ->assertDontSee('Todas las unidades');
    }

    public function test_filtra_por_fecha(): void
    {
        $this->consulta(['name' => 'Septiembre'], '2026-09-20 15:00:00');
        $this->consulta(['name' => 'Octubre'], '2026-10-04 15:00:00');

        $this->actingAs(User::factory()->create())
            ->get(route('inquiries.index', ['desde' => '2026-10-01', 'hasta' => '2026-10-31']))
            ->assertSee('Octubre')
            ->assertDontSee('Septiembre');
    }

    /**
     * Las 01:30 UTC del 6 son las 22:30 del 5 en Argentina: para el equipo,
     * esa consulta llego el 5, y asi tiene que filtrarse y mostrarse.
     */
    public function test_las_fechas_van_en_hora_argentina(): void
    {
        $this->consulta(['name' => 'De noche'], '2026-10-06 01:30:00');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('inquiries.index', ['desde' => '2026-10-05', 'hasta' => '2026-10-05']))
            ->assertSee('De noche')
            ->assertSee('22:30');

        $this->actingAs($user)
            ->get(route('inquiries.index', ['desde' => '2026-10-06', 'hasta' => '2026-10-06']))
            ->assertDontSee('De noche');
    }

    public function test_una_fecha_mal_escrita_no_rompe_el_listado(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('inquiries.index', ['desde' => 'ayer']))
            ->assertRedirect();
    }

    public function test_el_mensaje_no_imprime_html(): void
    {
        $this->consulta(['message' => '<script>alert(1)</script>']);

        $this->actingAs(User::factory()->create())
            ->get(route('inquiries.index'))
            ->assertDontSee('<script>alert(1)</script>', escape: false)
            ->assertSee('&lt;script&gt;', escape: false);
    }
}
