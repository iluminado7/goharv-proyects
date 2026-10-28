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
    case TareaCreada        = 'tarea_creada';
    case TareaEditada       = 'tarea_editada';
    case TareaCompletada    = 'tarea_completada';
    case TareaReabierta     = 'tarea_reabierta';
    case TareaEliminada     = 'tarea_eliminada';

    // Cuentas
    case CuentaDadaDeBaja   = 'cuenta_dada_de_baja';
    case CuentaReactivada   = 'cuenta_reactivada';
    case CuentaEditada      = 'cuenta_editada';

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
            self::TareaCreada        => 'Tarea creada',
            self::TareaEditada       => 'Tarea editada',
            self::TareaCompletada    => 'Tarea completada',
            self::TareaReabierta     => 'Tarea reabierta',
            self::TareaEliminada     => 'Tarea eliminada',
            self::CuentaDadaDeBaja   => 'Cuenta dada de baja',
            self::CuentaReactivada   => 'Cuenta reactivada',
            self::CuentaEditada      => 'Datos de cuenta editados',
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
            return 'var(--error)';
        }

        return match ($this) {
            self::ProyectoEliminado  => 'var(--error)',
            self::Ingreso            => 'var(--success)',
            self::ProyectoCreado     => 'var(--success)',
            self::EstadoCambiado     => 'var(--warning)',
            self::PrioridadCambiada  => 'var(--warning)',
            self::TareaCompletada    => 'var(--success)',
            self::TareaEliminada     => 'var(--error)',
            self::TareaReabierta     => 'var(--warning)',
            self::CuentaDadaDeBaja   => 'var(--error)',
            self::CuentaReactivada   => 'var(--success)',
            self::ProyectoArchivado  => 'var(--text-muted)',
            self::Salida             => 'var(--text-muted)',
            default                  => 'var(--accent)',
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
