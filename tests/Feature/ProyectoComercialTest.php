<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Enums\PaymentTerm;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lo comercial del proyecto: precio, moneda, condicion de pago y orden de
 * compra.
 *
 * La regla que vale la pena cuidar es quien lo ve. El profesional externo
 * trabaja adentro del proyecto como cualquiera, pero el precio es lo que paga
 * el cliente y no lo que cobra el: son dos numeros distintos y el segundo se
 * acuerda aparte. Por eso varios de los casos de abajo no prueban que algo
 * funcione, sino que algo no se vea y no se pueda escribir.
 */
class ProyectoComercialTest extends TestCase
{
    use RefreshDatabase;

    private function datos(array $extra = []): array
    {
        return array_merge([
            'name'     => 'Rediseño de marca',
            'status'   => 'nuevo',
            'priority' => 'media',
        ], $extra);
    }

    // --- Se guarda ---------------------------------------------------------

    public function test_un_socio_carga_lo_comercial(): void
    {
        $socio = User::factory()->create();

        $this->actingAs($socio)->post(route('projects.store'), $this->datos([
            'precio'         => '1250000.50',
            'moneda'         => 'ARS',
            'condicion_pago' => 'mitades',
            'orden_compra'   => 'OC-2026-0042',
        ]))->assertRedirect();

        $project = Project::firstWhere('name', 'Rediseño de marca');

        $this->assertSame('1250000.50', $project->precio);
        $this->assertSame(Currency::Ars, $project->moneda);
        $this->assertSame(PaymentTerm::Mitades, $project->condicion_pago);
        $this->assertSame('OC-2026-0042', $project->orden_compra);
    }

    public function test_un_proyecto_puede_no_tener_precio(): void
    {
        $socio = User::factory()->create();

        $this->actingAs($socio)->post(route('projects.store'), $this->datos())
            ->assertRedirect();

        $project = Project::firstWhere('name', 'Rediseño de marca');

        $this->assertNull($project->precio);
        $this->assertNull($project->precioFormateado());
    }

    // --- Como se muestra ---------------------------------------------------

    public function test_el_monto_va_en_formato_argentino(): void
    {
        $pesos   = new Project(['precio' => '1250000.50', 'moneda' => 'ARS']);
        $dolares = new Project(['precio' => '3500', 'moneda' => 'USD']);

        $this->assertSame('$ 1.250.000,50', $pesos->precioFormateado());
        $this->assertSame('US$ 3.500,00', $dolares->precioFormateado());
    }

    /** Un numero sin moneda no dice nada cuando el panel maneja las dos. */
    public function test_sin_moneda_no_hay_precio_que_mostrar(): void
    {
        $project = new Project(['precio' => '1000', 'moneda' => null]);

        $this->assertNull($project->precioFormateado());
    }

    public function test_el_socio_ve_el_precio_en_la_ficha(): void
    {
        $socio   = User::factory()->create();
        $project = Project::factory()->ownedBy($socio)->create([
            'precio' => '1250000.50',
            'moneda' => 'ARS',
        ]);

        $this->actingAs($socio)->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('$ 1.250.000,50');
    }

    // --- Lo que el externo no ve ni escribe --------------------------------

    public function test_el_externo_no_ve_el_precio_de_su_proyecto(): void
    {
        $externo = User::factory()->external()->create();
        $project = Project::factory()->ownedBy($externo)->create([
            'precio'         => '1250000.50',
            'moneda'         => 'ARS',
            'condicion_pago' => 'mitades',
            'orden_compra'   => 'OC-2026-0042',
        ]);

        $this->actingAs($externo)->get(route('projects.show', $project))
            ->assertOk()
            ->assertDontSee('1.250.000,50')
            ->assertDontSee('OC-2026-0042')
            ->assertDontSee('50% y 50%');
    }

    public function test_el_externo_no_ve_los_campos_en_el_formulario(): void
    {
        $externo = User::factory()->external()->create();
        $project = Project::factory()->ownedBy($externo)->create();

        $this->actingAs($externo)->get(route('projects.edit', $project))
            ->assertOk()
            ->assertDontSee('name="precio"', false)
            ->assertDontSee('name="orden_compra"', false);
    }

    /** Esconder el campo no alcanza: el POST a mano tambien tiene que rebotar. */
    public function test_el_externo_no_puede_cargar_el_precio_por_post(): void
    {
        $externo = User::factory()->external()->create();
        $project = Project::factory()->ownedBy($externo)->create([
            'precio' => '1000.00',
            'moneda' => 'ARS',
        ]);

        $this->actingAs($externo)->put(route('projects.update', $project), $this->datos([
            'name'           => $project->name,
            'precio'         => '99999.00',
            'moneda'         => 'USD',
            'condicion_pago' => 'contado',
            'orden_compra'   => 'INVENTADA',
        ]))->assertRedirect();

        $project->refresh();

        $this->assertSame('1000.00', $project->precio);
        $this->assertSame(Currency::Ars, $project->moneda);
        $this->assertNull($project->condicion_pago);
        $this->assertNull($project->orden_compra);
    }

    /** Tampoco puede vaciarlo, que es la otra forma de tocarlo. */
    public function test_el_externo_tampoco_puede_borrar_el_precio(): void
    {
        $externo = User::factory()->external()->create();
        $project = Project::factory()->ownedBy($externo)->create([
            'precio' => '1000.00',
            'moneda' => 'ARS',
        ]);

        $this->actingAs($externo)->put(route('projects.update', $project), $this->datos([
            'name'   => $project->name,
            'precio' => '',
            'moneda' => '',
        ]))->assertRedirect();

        $this->assertSame('1000.00', $project->refresh()->precio);
    }

    // --- Validacion --------------------------------------------------------

    public function test_el_precio_necesita_moneda(): void
    {
        $socio = User::factory()->create();

        $this->actingAs($socio)
            ->post(route('projects.store'), $this->datos(['precio' => '1000']))
            ->assertSessionHasErrors('moneda');
    }

    public function test_el_precio_no_puede_ser_negativo(): void
    {
        $socio = User::factory()->create();

        $this->actingAs($socio)
            ->post(route('projects.store'), $this->datos(['precio' => '-5', 'moneda' => 'ARS']))
            ->assertSessionHasErrors('precio');
    }

    public function test_la_moneda_tiene_que_ser_una_de_las_que_maneja_el_panel(): void
    {
        $socio = User::factory()->create();

        $this->actingAs($socio)
            ->post(route('projects.store'), $this->datos(['precio' => '1000', 'moneda' => 'EUR']))
            ->assertSessionHasErrors('moneda');
    }

    public function test_la_condicion_de_pago_tiene_que_ser_una_de_la_lista(): void
    {
        $socio = User::factory()->create();

        $this->actingAs($socio)
            ->post(route('projects.store'), $this->datos(['condicion_pago' => 'cuando_pueda']))
            ->assertSessionHasErrors('condicion_pago');
    }
}
