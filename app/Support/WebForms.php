<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Los formularios del sitio goharv.com.ar que llegan al panel (ver routes/api.php).
 *
 * "Contacto" es uno solo. Los de "Avisame" son uno por programa: el sitio manda
 * la etiqueta interna (`program`) y aca se le pone el titulo que se ve en la
 * web. Un programa nuevo que no este en la lista igual aparece en el panel,
 * con la etiqueta pasada en limpio, hasta que se sume aca.
 */
final class WebForms
{
    /** Valor de `?form=` para el formulario de contacto (es el que se ve primero). */
    public const CONTACT = 'contacto';

    /** Valor de `?form=` para el formulario de /academy/formacion-in-company. */
    public const IN_COMPANY = 'in-company';

    /** Valor de `?form=` para "Quiero analizar mi empresa" de /consulting. */
    public const CONSULTING = 'analizar-empresa';

    /**
     * Desafio que elige la persona en "Quiero analizar mi empresa" (clave que
     * manda el sitio => texto del sitio). Una clave nueva igual se guarda y se
     * muestra pasada en limpio, hasta que se sume aca.
     */
    public const CHALLENGES = [
        'crecimiento-expansion' => 'Crecimiento y expansión',
        'costos'                => 'Costos',
        'procesos'              => 'Procesos',
        'ventas'                => 'Ventas',
        'ia-automatizacion'     => 'IA y automatización',
        'legal'                 => 'Legal',
        'compliance'            => 'Compliance',
        'impuestos-finanzas'    => 'Impuestos/Finanzas',
        'franquicias'           => 'Franquicias',
        'otro'                  => 'Otro',
    ];

    /**
     * Las fechas se guardan en UTC (config/app.php). Para mostrarlas y para
     * filtrar por dia se usa la hora de Argentina: si no, una consulta de las
     * 22 h quedaria en el dia siguiente.
     */
    public const TIMEZONE = 'America/Argentina/Buenos_Aires';

    /** Idiomas del sitio (lo que llega en `locale`) => nombre para mostrar. */
    public const LOCALES = [
        'es' => 'Español',
        'en' => 'Inglés',
        'pt' => 'Portugués',
    ];

    /** Etiqueta interna del programa => titulo en el sitio. */
    public const PROGRAMS = [
        'programa-ia'             => 'Inteligencia Artificial para Empresas',
        'programa-compliance'     => 'Programa ejecutivo en compliance',
        'programa-franquicias'    => 'Programa ejecutivo en franquicias',
        'workshop-consorcios'     => 'Consorcios de propiedad horizontal',
        'workshop-access-control' => 'Business Workshop: Access control and smart locks',
    ];

    public static function programLabel(string $program): string
    {
        return self::PROGRAMS[$program] ?? Str::headline($program);
    }

    public static function challengeLabel(string $challenge): string
    {
        return self::CHALLENGES[$challenge] ?? Str::headline($challenge);
    }
}
