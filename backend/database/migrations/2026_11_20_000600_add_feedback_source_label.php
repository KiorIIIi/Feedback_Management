<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Optional collection label recorded with independent general feedback,
        // supplied by the shared public form link (?event=...) for events.
        Schema::table('general_feedback', function (Blueprint $t) {
            $t->string('source_label', 120)->nullable()->after('contact_email');
        });
    }

    public function down(): void
    {
        Schema::table('general_feedback', function (Blueprint $t) {
            $t->dropColumn('source_label');
        });
    }
};
