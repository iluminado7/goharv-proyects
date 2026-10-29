<?php

namespace App\Http\Controllers;

use App\Services\ArcaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Throwable;

/**
 * Endpoints de prueba para validar la integración con ARCA.
 * Solo para desarrollo: las rutas están protegidas por entorno en web.php.
 */
class ArcaTestController extends Controller
{
    private ?ArcaService $arca = null;

    /**
     * Se resuelve tarde y a mano, no por inyeccion en el constructor.
     *
     * ArcaService revienta si falta el access token, y Laravel arma las
     * dependencias del controlador antes de entrar al metodo: esa excepcion
     * pasaba por encima del try/catch de run() y salia un 500 crudo en vez
     * del JSON que dice que falta la variable. Pidiendolo adentro del
     * closure, el error cae donde se puede explicar.
     */
    private function arca(): ArcaService
    {
        return $this->arca ??= app(ArcaService::class);
    }

    /**
     * La pantalla de prueba. Es exploracion: no guarda nada, no tiene
     * permisos y solo existe en local. Sirve para ver que contesta ARCA con
     * distintos datos antes de disenar el modulo de facturacion.
     *
     * No usa layouts.app a proposito: estas rutas estan fuera de 'auth' y
     * ese layout da por hecho que hay una sesion abierta.
     */
    public function pantalla(Request $request): View
    {
        $conexion = null;
        $consulta = null;

        // Las dos consultas son explicitas y no automaticas: cada llamada a
        // ARCA tarda entre dos y cuatro segundos, y no vale la pena pagarlas
        // solo por abrir la pagina.
        if ($request->boolean('conexion')) {
            $conexion = $this->intentar(fn () => [
                'servidor'           => $this->arca()->estadoServidor(),
                'ultimo_comprobante' => $this->arca()->ultimoComprobante(),
            ]);
        }

        if ($numero = $request->integer('numero')) {
            $consulta = $this->intentar(fn () => $this->arca()->consultarComprobante($numero));
        }

        return view('arca.test', [
            'conexion'  => $conexion,
            'consulta'  => $consulta,
            'numero'    => $numero ?: null,
            'resultado' => session('resultado'),
            'config'    => config('arca'),
            'hoy'       => Carbon::now('America/Argentina/Buenos_Aires')->format('Ymd'),
        ]);
    }

    /** Emision desde el formulario. Vuelve a la pantalla con el resultado. */
    public function emitir(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'importe'  => ['required', 'numeric', 'min:0.01'],
            'doc_tipo' => ['required', 'integer', 'in:80,96,99'],
            'doc_nro'  => ['nullable', 'integer'],
            'concepto' => ['required', 'integer', 'in:1,2,3'],
            'cond_iva' => ['required', 'integer'],
            'fecha'    => ['nullable', 'digits:8'],
        ]);

        return back()->with('resultado', $this->intentar(fn () => $this->arca()->emitirFacturaC(
            importe:  (float) $data['importe'],
            docTipo:  (int) $data['doc_tipo'],
            docNro:   (int) ($data['doc_nro'] ?? 0),
            concepto: (int) $data['concepto'],
            condIva:  (int) $data['cond_iva'],
            fecha:    $data['fecha'] ?: null,
        )));
    }

    /** Como run(), pero devuelve el array en vez de una respuesta JSON. */
    protected function intentar(callable $fn): array
    {
        try {
            return ['ok' => true, 'data' => $fn()];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // GET /arca/test/estado → FEDummy
    public function estado(): JsonResponse
    {
        return $this->run(fn () => $this->arca()->estadoServidor());
    }

    // GET /arca/test/conexion → FEDummy + último comprobante
    public function conexion(): JsonResponse
    {
        return $this->run(fn () => [
            'servidor'           => $this->arca()->estadoServidor(),
            'ultimo_comprobante' => $this->arca()->ultimoComprobante(),
            'cuit'               => config('arca.cuit'),
            'pto_vta'            => config('arca.pto_vta'),
            'produccion'         => (bool) config('arca.production'),
        ]);
    }

    // POST /arca/test/factura  { "importe": 1500 }
    public function factura(Request $request): JsonResponse
    {
        $data = $request->validate([
            'importe'  => ['required', 'numeric', 'min:0.01'],
            'doc_tipo' => ['nullable', 'integer', 'in:80,96,99'],
            'doc_nro'  => ['nullable', 'integer'],
        ]);

        return $this->run(fn () => $this->arca()->emitirFacturaC(
            importe: $data['importe'],
            docTipo: $data['doc_tipo'] ?? 99,
            docNro:  $data['doc_nro'] ?? 0,
        ));
    }

    // GET /arca/test/factura/{numero} → FECompConsultar
    public function consultar(int $numero): JsonResponse
    {
        return $this->run(fn () => $this->arca()->consultarComprobante($numero));
    }

    protected function run(callable $fn): JsonResponse
    {
        try {
            return response()->json(['ok' => true, 'data' => $fn()]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
