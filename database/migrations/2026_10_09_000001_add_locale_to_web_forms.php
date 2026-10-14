<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Idioma del sitio desde el que llegó cada consulta o pedido (es, en, pt): el sitio
| goharv.com.ar es trilingüe. Nullable: lo que llegó antes de esta columna no lo tiene.
*/
return new class extends Migration
{
    public function up(): void
    {
        foreach (['contact_messages', 'leads'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('locale', 2)->nullable()->after('page');
            });
        }
    }

    public function down(): void
    {
        foreach (['contact_messages', 'leads'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('locale');
            });
        }
    }
};
