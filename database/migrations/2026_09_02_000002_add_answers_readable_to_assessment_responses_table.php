<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * What the visitor was shown, frozen at submit time.
 *
 * A response stores its answers by question id. The questions themselves may
 * be edited, reordered or replaced later, and a rebuilt question is a new row
 * with a new id — which would leave every older response unreadable on the
 * result page, in the CSV and on the contact timeline. The snapshot keeps the
 * question text, the chosen labels and the points as they were.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_responses', function (Blueprint $table) {
            $table->json('answers_readable')->nullable()->after('answers');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_responses', function (Blueprint $table) {
            $table->dropColumn('answers_readable');
        });
    }
};
