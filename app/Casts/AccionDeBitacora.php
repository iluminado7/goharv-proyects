<?php

namespace App\Casts;

use App\Enums\ActivityAction;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * El enum, pero sin reventar con un valor que ya no existe.
 *
 * El cast de enum que trae Laravel tira ValueError con cualquier valor que no
 * sea un caso, y eso voltea la pantalla entera: una sola fila rara y nadie ve
 * la bitacora. Y filas raras aparecen solas —una rama que suma una accion
 * propia, un renombre, un rollback—, porque en la base esto es una columna de
 * texto y el enum vive en el codigo.
 *
 * Activity::anotar() ya tiene la regla de que un log roto nunca puede voltear
 * la accion que lo origino. Esto la sostiene del otro lado: leer la bitacora
 * tampoco puede romperse por lo que hay guardado.
 *
 * Devuelve null cuando no reconoce el valor. El texto crudo sigue en la
 * columna y Activity lo muestra tal cual, porque un registro que no se
 * entiende es mejor que ninguno.
 *
 * @implements CastsAttributes<ActivityAction|null, ActivityAction|string|null>
 */
class AccionDeBitacora implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?ActivityAction
    {
        return is_string($value) ? ActivityAction::tryFrom($value) : null;
    }

    /** @return array<string, string|null> */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        return [$key => $value instanceof ActivityAction ? $value->value : $value];
    }
}
