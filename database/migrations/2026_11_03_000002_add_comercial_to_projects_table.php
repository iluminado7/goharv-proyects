<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Lo comercial del proyecto: cuanto, en que moneda, como se cobra y contra
 * que orden de compra del cliente.
 *
 * Nada de esto es fiscal todavia. Son datos del acuerdo, no de un
 * comprobante: viven aca y se cargan a mano, sin ARCA de por medio. El dia
 * que haya facturacion, la factura sera otra tabla que mira estos campos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            /*
             * decimal y no float: en coma flotante 0.1 + 0.2 no da 0.3, y con
             * plata eso termina en un centavo que no cierra. 14 digitos dan
             * hasta doce cifras enteras, de sobra para pesos.
             */
            $table->decimal('precio', 14, 2)->nullable()->after('client');

            // Nulas las cuatro: un proyecto puede existir sin precio acordado,
            // y de hecho los cinco que ya estan en la base no lo tienen.
            $table->string('moneda', 3)->nullable()->after('precio');
            $table->string('condicion_pago', 20)->nullable()->after('moneda');

            // El numero que manda el cliente. Es de el, asi que es texto: hay
            // quien usa "OC-2026-0042" y quien manda cuatro digitos sueltos.
            $table->string('orden_compra', 60)->nullable()->after('condicion_pago');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['precio', 'moneda', 'condicion_pago', 'orden_compra']);
        });
    }
};
