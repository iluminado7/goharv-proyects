<?php

namespace App\Services;

use Afip;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Wrapper de Afip SDK para el módulo de facturación.
 *
 * El resto de la app habla con esta clase, nunca con la librería directo.
 * Si mañana cambiás de SDK o vas contra el WSFE a mano, solo tocás acá.
 */
class ArcaService
{
    protected Afip $afip;

    protected int $ptoVta;

    protected int $cbteTipo;

    public function __construct()
    {
        $config = config('arca');

        if (empty($config['access_token'])) {
            throw new RuntimeException('Falta ARCA_ACCESS_TOKEN en el .env');
        }

        /*
         * Ultima traba, la que no depende de rutas ni de middleware: emitir
         * contra produccion desde una maquina que no es produccion seria
         * generar comprobantes fiscales reales por accidente, probando. Un
         * ARCA_PRODUCTION=true copiado sin querer de un .env a otro alcanza
         * para que pase.
         */
        if ($config['production'] && ! app()->environment('production')) {
            throw new RuntimeException(
                'ARCA_PRODUCTION esta en true fuera de produccion: los comprobantes '
                .'serian reales. Poné ARCA_PRODUCTION=false para probar.'
            );
        }

        $options = [
            'CUIT'         => $config['cuit'],
            'production'   => (bool) $config['production'],
            'access_token' => $config['access_token'],
        ];

        // Con certificado propio: la librería recibe el contenido, no la ruta.
        if (! empty($config['cert_path']) && ! empty($config['key_path'])) {
            $options['cert'] = $this->readFile($config['cert_path']);
            $options['key']  = $this->readFile($config['key_path']);
        }

        $this->afip     = new Afip($options);
        $this->ptoVta   = $config['pto_vta'];
        $this->cbteTipo = $config['cbte_tipo'];
    }

    /**
     * Estado de los servidores de ARCA (FEDummy).
     */
    public function estadoServidor(): object
    {
        return $this->afip->ElectronicBilling->GetServerStatus();
    }

    /**
     * Último número autorizado (FECompUltimoAutorizado).
     * Si responde, la autenticación y el punto de venta están OK.
     */
    public function ultimoComprobante(): int
    {
        return (int) $this->afip->ElectronicBilling->GetLastVoucher($this->ptoVta, $this->cbteTipo);
    }

    /**
     * Emite una Factura C a consumidor final o a un CUIT/DNI.
     *
     * @param  float        $importe   Total de la factura
     * @param  int          $docTipo   80 = CUIT, 96 = DNI, 99 = consumidor final sin identificar
     * @param  int          $docNro    0 si docTipo = 99
     * @param  int          $concepto  1 = productos, 2 = servicios, 3 = ambos
     * @param  int          $condIva   Condición IVA del receptor (5 = consumidor final, 6 = monotributo, 1 = RI)
     * @param  ?string       $fecha     Ymd. Por omisión hoy en Argentina.
     * @return array{numero:int, cae:string, vencimiento_cae:string}
     */
    public function emitirFacturaC(
        float $importe,
        int $docTipo = 99,
        int $docNro = 0,
        int $concepto = 2,
        int $condIva = 5,
        ?string $fecha = null,
    ): array {
        /*
         * La fecha va aparte y con zona horaria explícita: la app corre en
         * UTC y en Argentina eso adelanta el día tres horas antes de
         * medianoche, lo que fecharía comprobantes en el día equivocado.
         *
         * Se puede pasar otra, dentro de la ventana que acepta ARCA (±5 días
         * para productos, ±10 para servicios) y nunca anterior a la fecha
         * del último comprobante autorizado: los comprobantes van en orden
         * de fecha no decreciente, y violarlo da el error 10016.
         */
        $hoy = $fecha
            ? Carbon::createFromFormat('Ymd', $fecha, 'America/Argentina/Buenos_Aires')->startOfDay()
            : Carbon::now('America/Argentina/Buenos_Aires');
        $total = round($importe, 2);

        $data = [
            'CantReg'    => 1,
            'PtoVta'     => $this->ptoVta,
            'CbteTipo'   => $this->cbteTipo,
            'Concepto'   => $concepto,
            'DocTipo'    => $docTipo,
            'DocNro'     => $docNro,
            // CbteDesde y CbteHasta los pone CreateNextVoucher.
            'CbteFch'    => (int) $hoy->format('Ymd'),
            'ImpTotal'   => $total,
            'ImpTotConc' => 0,
            'ImpNeto'    => $total, // En Factura C todo va como neto, sin IVA discriminado
            'ImpOpEx'    => 0,
            'ImpIVA'     => 0,
            'ImpTrib'    => 0,
            'MonId'      => 'PES',
            'MonCotiz'   => 1,
            'CondicionIVAReceptorId' => $condIva,
        ];

        // Servicios: ARCA exige período facturado y vencimiento de pago
        if ($concepto !== 1) {
            $data['FchServDesde'] = (int) $hoy->copy()->startOfMonth()->format('Ymd');
            $data['FchServHasta'] = (int) $hoy->format('Ymd');
            $data['FchVtoPago']   = (int) $hoy->format('Ymd');
        }

        $res = $this->autorizar($data);

        return [
            // El numero sale de la respuesta, no de lo que supusimos antes
            // de mandar: es el que ARCA autorizo de verdad.
            'numero'          => (int) $res['voucher_number'],
            'cae'             => $res['CAE'],
            'vencimiento_cae' => $res['CAEFchVto'],
        ];
    }

    /**
     * Manda el comprobante, reintentando si otro se adelanto con el numero.
     *
     * El numero de comprobante no lo elige uno: hay que preguntarle a ARCA
     * cual fue el ultimo autorizado y pedir el siguiente. Entre esas dos
     * llamadas puede colarse otro y entonces ARCA contesta 10016, "el numero
     * no se corresponde con el proximo a autorizar".
     *
     * Con el CUIT de prueba compartido pasa seguido, porque lo usa mucha
     * gente a la vez. Con un CUIT propio pasa igual el dia que dos personas
     * del equipo facturen al mismo tiempo: la carrera es la misma, solo que
     * mas dificil de encontrar. Por eso el reintento se queda cuando pasemos
     * a produccion.
     *
     * Solo se reintenta el 10016. Cualquier otro error es un problema de los
     * datos y repetirlo no lo arregla.
     */
    protected function autorizar(array $data, int $intentos = 4): array
    {
        for ($i = 1; ; $i++) {
            try {
                return $this->afip->ElectronicBilling->CreateNextVoucher($data);
            } catch (\Throwable $e) {
                if ($i >= $intentos || ! str_contains($e->getMessage(), '10016')) {
                    throw $e;
                }

                // Una espera corta y creciente: si hay varios peleando por el
                // mismo numero, reintentar todos a la vez repite el choque.
                usleep($i * 250_000);
            }
        }
    }

    /**
     * Consulta un comprobante ya emitido (FECompConsultar).
     */
    public function consultarComprobante(int $numero): ?object
    {
        return $this->afip->ElectronicBilling->GetVoucherInfo($numero, $this->ptoVta, $this->cbteTipo);
    }

    protected function readFile(string $path): string
    {
        $full = is_file($path) ? $path : storage_path('app/' . ltrim($path, '/'));

        if (! is_file($full)) {
            throw new RuntimeException("No encuentro el archivo: {$path}");
        }

        return file_get_contents($full);
    }
}
