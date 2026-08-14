<?php

use App\Enums\DocumentScanStatus;
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
        Schema::table('documents', function (Blueprint $table) {
            // État de l'analyse antivirus. La règle mimes rejette déjà un
            // exécutable déguisé, mais pas un PDF légitimement formé porteur
            // de code : ces fichiers sont ouverts chaque jour par le cabinet.
            $table->string('scan_status')->default(DocumentScanStatus::EnAttente->value)->after('size');
            $table->timestamp('scanned_at')->nullable()->after('scan_status');

            $table->index('scan_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['scan_status']);
            $table->dropColumn(['scan_status', 'scanned_at']);
        });
    }
};
