<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La busqueda con LIKE '%...%' no usa indice. MySQL y PostgreSQL soportan
     * fulltext; SQLite (los tests) no, y ahi se sigue usando LIKE.
     */
    public function up(): void
    {
        if (! $this->supported()) {
            return;
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->fullText(['name', 'description'], 'projects_search_fulltext');
        });
    }

    public function down(): void
    {
        if (! $this->supported()) {
            return;
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->dropFullText('projects_search_fulltext');
        });
    }

    private function supported(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb', 'pgsql'], true);
    }
};
