<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Named, independently shareable feedback forms for events. Each has its
        // own unguessable public token plus an optional acceptance window.
        Schema::create('feedback_forms', function (Blueprint $t) {
            $t->id();
            $t->string('token', 32)->unique();
            $t->foreignId('created_by')->constrained('users');
            $t->string('title', 200);
            $t->string('description', 1000)->nullable();
            $t->timestamp('opens_at')->nullable();
            $t->timestamp('closes_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::table('general_feedback', function (Blueprint $t) {
            $t->foreignId('feedback_form_id')->nullable()->constrained('feedback_forms');
        });
    }

    public function down(): void
    {
        Schema::table('general_feedback', function (Blueprint $t) {
            $t->dropConstrainedForeignId('feedback_form_id');
        });
        Schema::dropIfExists('feedback_forms');
    }
};
