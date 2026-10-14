<?php

namespace Tests\Feature;

use App\Models\ConsultingRequest;
use App\Models\ContactMessage;
use App\Models\InCompanyRequest;
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

    private function inCompany(array $attrs = []): InCompanyRequest
    {
        return InCompanyRequest::create(array_merge([
            'name'         => 'Laura Díaz',
            'role'         => 'Gerenta de RRHH',
            'company'      => 'Industrias del Sur SA',
            'email'        => 'laura@industrias.com.ar',
            'phone'        => '1145678901',
            'team_size'    => '31–100',
            'areas'        => ['Compliance', 'Gestión comercial'],
            'modality'     => 'Presencial',
            'message'      => 'Queremos capacitar a los mandos medios.',
            'page'         => '/academy/formacion-in-company',
            'consented_at' => now(),
        ], $attrs));
    }

    private function consulting(array $attrs = []): ConsultingRequest
    {
        return ConsultingRequest::create(array_merge([
            'challenge'    => 'ia-automatizacion',
            'goal'         => 'Automatizar la atención al cliente.',
            'name'         => 'Pablo Ruiz',
            'role'         => 'Director',
            'company'      => 'Ruiz Hnos SRL',
            'email'        => 'pablo@ruiz.com.ar',
            'phone'        => '3415551234',
            'page'         => '/consulting',
            'consented_at' => now(),
        ], $attrs));
    }

    public function test_formacion_in_company_tiene_su_seccion_con_sus_datos(): void
    {
        $this->consulta(['name' => 'Ana Contacto']);
        $this->inCompany(['name' => 'Laura InCompany']);

        $this->actingAs(User::factory()->create())
            ->get(route('inquiries.index', ['form' => 'in-company']))
            ->assertOk()
            ->assertSee('<h1 class="page" id="inq-title">Formación In Company</h1>', escape: false)
            ->assertSee('Laura InCompany')
            ->assertSee('Industrias del Sur SA · Gerenta de RRHH')
            ->assertSee('31–100 colaboradores')
            ->assertSee('Gestión comercial')
            ->assertSee('Queremos capacitar a los mandos medios.')
            ->assertSee('modalidad: Presencial')
            ->assertDontSee('Ana Contacto');
    }

    public function test_analizar_mi_empresa_tiene_su_seccion_con_el_desafio(): void
    {
        $this->consulta(['name' => 'Ana Contacto']);
        $this->consulting(['name' => 'Pablo Consulting']);

        $this->actingAs(User::factory()->create())
            ->get(route('inquiries.index', ['form' => 'analizar-empresa']))
            ->assertOk()
            ->assertSee('<h1 class="page" id="inq-title">Analizar mi empresa</h1>', escape: false)
            ->assertSee('Pablo Consulting')
            ->assertSee('Ruiz Hnos SRL · Director')
            ->assertSee('IA y automatización')
            ->assertSee('Automatizar la atención al cliente.')
            ->assertDontSee('Ana Contacto');
    }

    public function test_los_contadores_incluyen_las_secciones_nuevas(): void
    {
        $this->inCompany();
        $this->inCompany();
        $this->consulting();

        $this->actingAs(User::factory()->create())
            ->get(route('inquiries.index'))
            ->assertSeeInOrder(['<span class="inq-link-l">Formación In Company</span>', '<span class="inq-count">2</span>'], escape: false)
            ->assertSeeInOrder(['<span class="inq-link-l">Analizar mi empresa</span>', '<span class="inq-count">1</span>'], escape: false);
    }

    public function test_filtra_in_company_por_empresa_y_colaboradores(): void
    {
        $this->inCompany(['name' => 'Chica Acme', 'company' => 'Acme', 'team_size' => '1–10']);
        $this->inCompany(['name' => 'Grande Acme', 'company' => 'Acme', 'team_size' => 'Más de 100']);
        $this->inCompany(['name' => 'Grande Otra', 'company' => 'Otra SRL', 'team_size' => 'Más de 100']);

        $this->actingAs(User::factory()->create())
            ->get(route('inquiries.index', ['form' => 'in-company', 'empresa' => 'Acme', 'tamano' => 'Más de 100']))
            ->assertSee('Todos los tamaños')
            ->assertSee('Grande Acme')
            ->assertDontSee('Chica Acme')
            ->assertDontSee('Grande Otra');
    }

    public function test_filtra_analizar_mi_empresa_por_desafio(): void
    {
        $this->consulting(['name' => 'Quiere IA', 'challenge' => 'ia-automatizacion']);
        $this->consulting(['name' => 'Quiere Costos', 'challenge' => 'costos']);

        $this->actingAs(User::factory()->create())
            ->get(route('inquiries.index', ['form' => 'analizar-empresa', 'desafio' => 'costos']))
            ->assertSee('Todos los desafíos')
            ->assertSee('Quiere Costos')
            ->assertDontSee('Quiere IA');
    }

    /** Un desafio que el panel no conoce se muestra pasado en limpio. */
    /** Los formularios van agrupados: primero las consultas, despues los "Avisame". */
    public function test_la_barra_lateral_agrupa_consultas_y_avisame(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('inquiries.index', ['form' => 'in-company']))
            ->assertSeeInOrder(['Consultas', 'Contacto', 'Formación In Company', 'Analizar mi empresa', 'Avisame', 'Inteligencia Artificial para Empresas'])
            ->assertSee('aria-current="page"', escape: false)
            ->assertSee('0 consultas');
    }

    public function test_un_desafio_desconocido_se_ve_igual(): void
    {
        $this->consulting(['challenge' => 'sucesion-familiar']);

        $this->actingAs(User::factory()->create())
            ->get(route('inquiries.index', ['form' => 'analizar-empresa']))
            ->assertSee('Sucesion Familiar');
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
            ->assertSee('<h1 class="page" id="inq-title">Consorcios de propiedad horizontal</h1>', escape: false)
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
            ->assertSeeInOrder(['<span class="inq-link-l">Contacto</span>', '<span class="inq-count">2</span>'], escape: false)
            ->assertSeeInOrder(['<span class="inq-link-l">Consorcios de propiedad horizontal</span>', '<span class="inq-count">1</span>'], escape: false)
            ->assertSeeInOrder(['<span class="inq-link-l">Business Workshop: Access control and smart locks</span>', '<span class="inq-count">0</span>'], escape: false);
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

    /** Cada consulta muestra el idioma del sitio desde el que llegó (las viejas, sin idioma, nada) */
    public function test_muestra_el_idioma_de_cada_consulta(): void
    {
        $this->consulta(['name' => 'Desde Brasil', 'locale' => 'pt']);
        $this->consulta(['name' => 'Sin idioma', 'locale' => null]);

        $this->actingAs(User::factory()->create())
            ->get(route('inquiries.index'))
            ->assertSee('title="Portugués">PT</span>', escape: false)
            ->assertDontSee('>ES</span>', escape: false);
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
