<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('portal_notifications', function (Blueprint $table) {
            $table->enum('kind', ['request', 'feedback', 'evaluation'])->default('request');
            $table->foreignId('general_feedback_id')->nullable()->constrained('general_feedback');
            $table->index(['user_id', 'read_at', 'id'], 'notification_inbox');
        });
    }

    public function down(): void
    {
        Schema::table('portal_notifications', function (Blueprint $table) {
            $table->dropIndex('notification_inbox');
            $table->dropConstrainedForeignId('general_feedback_id');
            $table->dropColumn('kind');
        });
    }
};
