<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conservation : plus aucune suppression sans décision humaine.
 *
 * L'échéance est matérialisée en base plutôt que recalculée à chaque lecture.
 * Elle devient alors une simple date à comparer — ce qui permet de repousser
 * une échéance de douze mois sans toucher à l'activité du dossier, et de savoir
 * exactement quand envoyer un rappel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // null tant que le dossier n'est pas arrivé à échéance.
            $table->string('retention_state')->nullable()->after('privacy_version');

            $table->timestamp('retention_due_at')->nullable()->after('retention_state');

            // Dernier rappel envoyé aux administrateurs, pour ne pas les
            // inonder tout en n'arrêtant jamais de relancer.
            $table->timestamp('retention_reminded_at')->nullable()->after('retention_due_at');

            $table->index(['retention_state', 'retention_due_at']);
        });

        // Les dossiers déjà en base reçoivent une échéance calculée depuis leur
        // dernière activité connue, comme s'ils avaient toujours eu ce champ.
        //
        // En PHP plutôt qu'en SQL : DATE_ADD n'existe pas sur SQLite, sur
        // laquelle tourne la suite de tests.
        $mois = (int) config('retention.months', 36);

        DB::table('applications')->orderBy('id')->chunkById(500, function (Collection $lignes) use ($mois): void {
            foreach ($lignes as $ligne) {
                DB::table('applications')
                    ->where('id', $ligne->id)
                    ->update([
                        'retention_due_at' => Carbon::parse((string) $ligne->updated_at)->addMonths($mois),
                    ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex(['retention_state', 'retention_due_at']);
            $table->dropColumn(['retention_state', 'retention_due_at', 'retention_reminded_at']);
        });
    }
};
