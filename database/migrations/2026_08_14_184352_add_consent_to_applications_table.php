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
        Schema::table('applications', function (Blueprint $table) {
            // Quand le candidat a accepté la politique de confidentialité…
            $table->timestamp('consented_at')->nullable()->after('ip_address');
            // …et quelle version du texte il a acceptée. Sans elle, un
            // consentement recueilli n'est pas opposable : on ne saurait pas
            // à quoi le candidat a consenti.
            $table->string('privacy_version', 20)->nullable()->after('consented_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['consented_at', 'privacy_version']);
        });
    }
};
