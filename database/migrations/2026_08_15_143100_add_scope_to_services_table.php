<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tarif indicatif, et surtout : ce qui est compris et ce qui ne l'est pas.
 *
 * Le flou sur le périmètre est le principal terrain des litiges dans ce
 * secteur. Dire explicitement ce qui n'est pas inclus protège le candidat
 * autant que le cabinet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('price_note')->nullable()->after('body');
            $table->json('included')->nullable()->after('price_note');
            $table->json('excluded')->nullable()->after('included');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['price_note', 'included', 'excluded']);
        });
    }
};
