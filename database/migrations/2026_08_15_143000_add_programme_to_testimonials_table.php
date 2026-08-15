<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le programme obtenu, à côté du prénom et du pays.
 *
 * Un témoignage sans contexte ne rassure personne : « Entrée Express, 2025 »
 * en dit plus long qu'un paragraphe élogieux anonyme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->string('author_programme')->nullable()->after('author_country');
        });
    }

    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropColumn('author_programme');
        });
    }
};
