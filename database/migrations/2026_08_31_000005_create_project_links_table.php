<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('label', 60);
            $table->string('url', 500);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'position']);
        });

        // El enlace unico que habia en projects.url pasa a ser el primero de la lista.
        if (Schema::hasColumn('projects', 'url')) {
            DB::table('projects')
                ->whereNotNull('url')
                ->where('url', '!=', '')
                ->orderBy('id')
                ->each(function ($project) {
                    DB::table('project_links')->insert([
                        'project_id' => $project->id,
                        'label'      => 'Principal',
                        'url'        => $project->url,
                        'position'   => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });

            Schema::table('projects', function (Blueprint $table) {
                $table->dropColumn('url');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('projects', 'url')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->string('url')->nullable()->after('description');
            });

            DB::table('project_links')
                ->where('position', 0)
                ->orderBy('id')
                ->each(function ($link) {
                    DB::table('projects')->where('id', $link->project_id)->update(['url' => $link->url]);
                });
        }

        Schema::dropIfExists('project_links');
    }
};
