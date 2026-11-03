<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Las fechas que ya estan en la base fueron escritas en UTC, porque hasta hoy
 * el panel corria en UTC. A partir de ahora corre en hora argentina, asi que
 * los valores guardados hay que correrlos: si no, todo lo viejo se leeria tres
 * horas mas tarde de lo que realmente paso, y las dos epocas convivirian en la
 * misma columna sin forma de distinguirlas.
 *
 * Son tres horas parejas y no un calculo por fecha porque Argentina es UTC-3
 * todo el ano: no hay horario de verano desde 2009, y no hay datos anteriores.
 * En un pais con cambio de hora esto estaria mal y habria que convertir fila
 * por fila.
 */
return new class extends Migration
{
    /**
     * Las columnas que guardan un instante.
     *
     * Las columnas `date` —projects.due_date y tasks.due_date— quedan afuera a
     * proposito. Una fecha de entrega es un dia del calendario que eligio una
     * persona, no un momento en el tiempo: restarle tres horas la mandaria al
     * dia anterior, que es justo el error que venimos a arreglar.
     *
     * failed_jobs.failed_at tambien queda afuera: es ruido operativo, nadie lo
     * lee contra un reloj y no vale ensuciar la migracion con el.
     */
    private const COLUMNAS = [
        'users'                 => ['created_at', 'updated_at', 'email_verified_at', 'avatar_updated_at'],
        'password_reset_tokens' => ['created_at'],
        'projects'              => ['created_at', 'updated_at', 'deleted_at', 'started_at', 'completed_at'],
        'project_user'          => ['created_at', 'updated_at'],
        'project_updates'       => ['created_at', 'updated_at'],
        'project_links'         => ['created_at', 'updated_at'],
        'user_avatars'          => ['created_at', 'updated_at'],
        'activity_logs'         => ['created_at'],
        'notifications'         => ['created_at', 'updated_at', 'read_at'],
        'tasks'                 => ['created_at', 'updated_at', 'completed_at'],
        'task_user'             => ['created_at', 'updated_at'],
        'push_subscriptions'    => ['created_at', 'updated_at'],

        /*
         * Estas son de la rama formularios, que todavia no esta integrada. En
         * produccion no existen y el guard de abajo las saltea sin hacer nada.
         * Estan igual porque en la base local si existen con datos, y si no se
         * corren ahora quedan tres horas adelantadas para siempre: cuando esa
         * rama se integre, la migracion ya habria pasado y nadie se acordaria.
         */
        'leads'                 => ['created_at', 'updated_at', 'consented_at'],
        'contact_messages'      => ['created_at', 'updated_at', 'consented_at'],
        'consulting_requests'   => ['created_at', 'updated_at', 'consented_at'],
        'in_company_requests'   => ['created_at', 'updated_at', 'consented_at'],
        'compliance_requests'   => ['created_at', 'updated_at'],
    ];

    public function up(): void
    {
        $this->correr(-3);
    }

    public function down(): void
    {
        $this->correr(3);
    }

    private function correr(int $horas): void
    {
        foreach (self::COLUMNAS as $tabla => $columnas) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }

            foreach ($columnas as $columna) {
                if (! Schema::hasColumn($tabla, $columna)) {
                    continue;
                }

                DB::table($tabla)
                    ->whereNotNull($columna)
                    ->update([$columna => DB::raw($this->expresion($columna, $horas))]);
            }
        }
    }

    /** MySQL en produccion, SQLite en los tests: la cuenta es la misma. */
    private function expresion(string $columna, int $horas): string
    {
        $signo = $horas < 0 ? '-' : '+';
        $cuantas = abs($horas);

        return match (DB::getDriverName()) {
            'sqlite' => "datetime({$columna}, '{$signo}{$cuantas} hours')",
            default  => "{$columna} {$signo} INTERVAL {$cuantas} HOUR",
        };
    }
};
