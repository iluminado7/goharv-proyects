<?php

namespace App\Http\Controllers;

use App\Services\ArcaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
