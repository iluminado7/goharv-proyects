<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los contadores de arriba del tablero son el filtro por estado. Antes eran
 * texto suelto y encima mentían: el total respetaba los filtros y los números
 * por estado no, así que al filtrar por empresa no cerraban entre sí.
 */
class BoardFilterTest extends TestCase
{
    use RefreshDatabase;

    private function tablero(): void
    {
        Project::factory()->forClient('Alfa')->status(ProjectStatus::Nuevo)->create(['name' => 'Alfa nuevo']);
        Project::factory()->forClient('Alfa')->status(ProjectStatus::Terminado)->create(['name' => 'Alfa terminado']);
        Project::factory()->forClient('Beta')->status(ProjectStatus::Nuevo)->create(['name' => 'Beta nuevo']);
        Project::factory()->forClient('Beta')->status(ProjectStatus::Nuevo)->create(['name' => 'Beta otro nuevo']);
    }

    public function test_cada_contador_lleva_a_su_estado(): void
    {
        $this->tablero();

        $this->actingAs(User::factory()->create())
            ->get(route('projects.index', ['status' => ProjectStatus::Terminado->value]))
            ->assertOk()
            ->assertSee('Alfa terminado')
            ->assertDontSee('Alfa nuevo')
            ->assertDontSee('Beta nuevo');
    }

    public function test_los_contadores_acompanan_al_resto_de_los_filtros(): void
    {
        $this->tablero();

        $this->actingAs(User::factory()->create())
            ->get(route('projects.index', ['client' => 'Beta']))
            ->assertOk()
            ->assertViewHas('total', 2)
            ->assertViewHas('counts', fn ($counts) => ($counts[ProjectStatus::Nuevo->value] ?? 0) === 2
                && ($counts[ProjectStatus::Terminado->value] ?? 0) === 0);
    }

    /** Si el contador de un estado cambiara al pararse en él, no se podría saltar de uno a otro. */
    public function test_los_numeros_no_cambian_al_pararse_en_un_estado(): void
    {
        $this->tablero();
        $user = User::factory()->create();

        $sinFiltro = $this->actingAs($user)->get(route('projects.index'));
        $enNuevo   = $this->actingAs($user)->get(route('projects.index', ['status' => ProjectStatus::Nuevo->value]));

        $this->assertEquals(
            $sinFiltro->viewData('counts')->all(),
            $enNuevo->viewData('counts')->all()
        );
        $this->assertSame($sinFiltro->viewData('total'), $enNuevo->viewData('total'));
    }

    public function test_el_contador_activo_queda_marcado(): void
    {
        $this->tablero();

        $html = $this->actingAs(User::factory()->create())
            ->get(route('projects.index', ['status' => ProjectStatus::Nuevo->value]))
            ->getContent();

        $this->assertSame(1, substr_count($html, 'aria-current="true"'));
        $this->assertStringContainsString('tally-item on', $html);
    }

    public function test_al_filtrar_por_estado_no_se_pierde_la_empresa(): void
    {
        $this->tablero();

        $html = $this->actingAs(User::factory()->create())
            ->get(route('projects.index', ['client' => 'Beta']))
            ->getContent();

        // Los enlaces de los contadores tienen que arrastrar el filtro vigente.
        $this->assertStringContainsString('client=Beta&amp;status=nuevo', $html);
    }

    public function test_la_fila_de_chips_ya_no_duplica_el_filtro(): void
    {
        $this->tablero();

        $html = $this->actingAs(User::factory()->create())
            ->get(route('projects.index'))
            ->getContent();

        $this->assertStringNotContainsString('class="chip ', $html);
    }
}
