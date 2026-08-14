<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('page_sections', function (Blueprint $table) {
            $table->id();
            // La page à laquelle appartient le bloc : accueil, a-propos…
            $table->string('page');
            // Le type de bloc décide de la structure de « data » et du partial
            // Blade qui le rend. Un type inconnu est ignoré, jamais fatal.
            $table->string('type');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->json('data');
            $table->timestamps();

            $table->index(['page', 'is_published', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_sections');
    }
};
