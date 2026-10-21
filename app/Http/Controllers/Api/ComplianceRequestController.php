<?php

namespace App\Http\Controllers\Api;

use App\Models\ComplianceRequest;

/**
 * Recibe "Quiero fortalecer mi empresa" de /compliance. El sitio usa el mismo
 * formulario que /consulting (ConsultingForm), con sus propias opciones en el
 * paso 1: mismos campos, misma validacion y las mismas dos formas de llegar
 * (JSON con JavaScript, envio comun sin JavaScript). Solo cambia donde se guarda.
 */
class ComplianceRequestController extends ConsultingRequestController
{
    protected string $model = ComplianceRequest::class;
}
