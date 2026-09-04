<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La consultora trabaja para varias empresas y hasta ahora el cliente venia
 * metido dentro del nombre del proyecto ("Canal de Denuncias - RiseUp").
 * Con columna propia se puede filtrar el tablero por empresa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('client', 120)->nullable()->after('description');
            $table->index('client');
        });

        if (! $this->fulltext()) {
            return;
        }

        // El indice de busqueda pasa a cubrir tambien el nombre de la empresa.
        Schema::table('projects', function (Blueprint $table) {
            $table->dropFullText('projects_search_fulltext');
            $table->fullText(['name', 'description', 'client'], 'projects_search_fulltext');
        });
    }

    public function down(): void
    {
        if ($this->fulltext()) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropFullText('projects_search_fulltext');
            });
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['client']);
            $table->dropColumn('client');
        });

        if ($this->fulltext()) {
            Schema::table('projects', function (Blueprint $table) {
                $table->fullText(['name', 'description'], 'projects_search_fulltext');
            });
        }
    }

    private function fulltext(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb', 'pgsql'], true);
    }
};
