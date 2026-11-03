<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * El panel corre en hora argentina.
 *
 * Con UTC, entre las 21 y la medianoche de Argentina la aplicacion ya estaba
 * en el dia siguiente. Eso no era una molestia de presentacion: una tarea que
 * vencia hoy figuraba vencida tres horas antes de tiempo, y lo que alguien
 * cargaba a la noche quedaba fechado manana.
 *
 * Por eso casi todos los casos de abajo viajan a las 22, que es la unica hora
 * a la que el error se ve. A las 10 de la manana todo esto pasa igual con UTC.
 */
class ZonaHorariaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Las 22 del 3 de noviembre en Argentina.
     *
     * Va escrito como el instante UTC equivalente, y no como Carbon::create(
     * 2026, 11, 3, 22) a secas, porque esa forma toma la zona horaria de la
     * app: al volver la config a UTC el escenario se movia con ella y los
     * tests pasaban igual, sin probar nada. Clavado en UTC, el momento es el
     * mismo siempre y lo unico que cambia es como lo lee el panel.
     */
    private function laNocheDelTres(): Carbon
    {
        return Carbon::create(2026, 11, 4, 1, 0, 0, 'UTC');
    }

    public function test_la_app_corre_en_hora_argentina(): void
    {
        $this->assertSame('America/Argentina/Buenos_Aires', config('app.timezone'));
    }

    public function test_el_desfase_con_utc_es_de_tres_horas(): void
    {
        $this->assertSame(-3 * 3600, now()->getOffset());
    }

    public function test_lo_que_se_carga_de_noche_queda_con_la_fecha_de_hoy(): void
    {
        $this->travelTo($this->laNocheDelTres());

        $project = Project::factory()->create();

        $this->assertSame('2026-11-03', $project->created_at->toDateString());
    }

    public function test_una_tarea_que_vence_hoy_no_figura_vencida_a_las_diez_de_la_noche(): void
    {
        $this->travelTo($this->laNocheDelTres());

        Task::factory()->dueOn('2026-11-03')->create();

        $this->assertSame(0, Task::vencidas()->count());
    }

    public function test_una_tarea_que_vence_manana_tampoco(): void
    {
        $this->travelTo($this->laNocheDelTres());

        $task = Task::factory()->dueOn('2026-11-04')->create();

        $this->assertFalse($task->isOverdue());
    }

    /** Lo que de verdad vencio sigue vencido: el arreglo no apaga el aviso. */
    public function test_una_tarea_de_ayer_si_figura_vencida(): void
    {
        $this->travelTo($this->laNocheDelTres());

        $task = Task::factory()->dueOn('2026-11-02')->create();

        $this->assertSame(1, Task::vencidas()->count());
        $this->assertTrue($task->isOverdue());
    }

    // --- La migracion que corrio lo que ya estaba guardado -----------------

    private function migracion(): object
    {
        return require database_path(
            'migrations/2026_11_03_000001_pasar_fechas_guardadas_a_hora_argentina.php'
        );
    }

    public function test_la_migracion_atrasa_tres_horas_los_instantes(): void
    {
        $project = Project::factory()->create();

        DB::table('projects')->where('id', $project->id)
            ->update(['created_at' => '2026-11-04 01:00:00']);

        $this->migracion()->up();

        $this->assertSame(
            '2026-11-03 22:00:00',
            Project::find($project->id)->created_at->toDateTimeString(),
        );
    }

    /**
     * El caso que justifica la lista explicita de columnas: due_date es un dia
     * del calendario, no un instante. Si la migracion la tocara, una entrega
     * del 3 pasaria a ser del 2.
     */
    public function test_la_migracion_no_toca_las_fechas_de_entrega(): void
    {
        $project = Project::factory()->create(['due_date' => '2026-11-03']);

        $this->migracion()->up();

        $this->assertSame('2026-11-03', Project::find($project->id)->due_date->toDateString());
    }

    public function test_la_migracion_se_puede_deshacer(): void
    {
        $project = Project::factory()->create();

        DB::table('projects')->where('id', $project->id)
            ->update(['created_at' => '2026-11-04 01:00:00']);

        $migracion = $this->migracion();
        $migracion->up();
        $migracion->down();

        $this->assertSame(
            '2026-11-04 01:00:00',
            Project::find($project->id)->created_at->toDateTimeString(),
        );
    }

    /** Una columna nula se queda nula y no explota. */
    public function test_la_migracion_aguanta_las_columnas_vacias(): void
    {
        $project = Project::factory()->create();

        DB::table('projects')->where('id', $project->id)
            ->update(['completed_at' => null, 'started_at' => null]);

        $this->migracion()->up();

        $this->assertNull(Project::find($project->id)->completed_at);
    }
}
