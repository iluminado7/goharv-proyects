<?php

namespace App\Enums;

enum ActivityAction: string
{
    // Acceso
    case Ingreso           = 'ingreso';
    case ClaveIncorrecta   = 'clave_incorrecta';
    case CorreoInexistente = 'correo_inexistente';
    case CuentaDeBaja      = 'cuenta_de_baja';
    case Salida            = 'salida';

    // Proyectos
    case ProyectoCreado     = 'proyecto_creado';
    case ProyectoArchivado  = 'proyecto_archivado';
    case ProyectoRestaurado = 'proyecto_restaurado';
    case ProyectoEliminado  = 'proyecto_eliminado';
    case EstadoCambiado     = 'estado_cambiado';
    case PrioridadCambiada  = 'prioridad_cambiada';
    case NotaNueva          = 'nota_nueva';

    public function label(): string
    {
        return match ($this) {
            self::Ingreso            => 'Ingreso',
            self::ClaveIncorrecta    => 'Clave incorrecta',
            self::CorreoInexistente  => 'Correo inexistente',
            self::CuentaDeBaja       => 'Intento con cuenta de baja',
            self::Salida             => 'Cierre de sesión',
            self::ProyectoCreado     => 'Proyecto creado',
            self::ProyectoArchivado  => 'Proyecto archivado',
            self::ProyectoRestaurado => 'Proyecto restaurado',
            self::ProyectoEliminado  => 'Proyecto eliminado',
            self::EstadoCambiado     => 'Cambio de estado',
            self::PrioridadCambiada  => 'Cambio de prioridad',
            self::NotaNueva          => 'Nota nueva',
        };
    }

    /** Los intentos fallidos se miran distinto al resto: van en rojo. */
    public function esFallo(): bool
    {
        return in_array($this, [self::ClaveIncorrecta, self::CorreoInexistente, self::CuentaDeBaja], true);
    }

    public function color(): string
    {
        if ($this->esFallo()) {
            return '#E05C4B';
        }

        return match ($this) {
            self::ProyectoEliminado  => '#E05C4B',
            self::Ingreso            => '#4FA97C',
            self::ProyectoCreado     => '#4FA97C',
            self::EstadoCambiado     => '#E0A33E',
            self::PrioridadCambiada  => '#E0A33E',
            self::ProyectoArchivado  => '#7E838C',
            self::Salida             => '#7E838C',
            default                  => '#6BA5E7',
        };
    }

    /** Agrupadas para el filtro de la pantalla. */
    public static function acceso(): array
    {
        return [self::Ingreso, self::ClaveIncorrecta, self::CorreoInexistente, self::CuentaDeBaja, self::Salida];
    }

    public static function proyectos(): array
    {
        // array_diff() compara convirtiendo a texto y un enum no se deja:
        // hay que filtrar comparando los casos en si.
        return array_values(array_filter(
            self::cases(),
            fn (self $caso) => ! in_array($caso, self::acceso(), true),
        ));
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
