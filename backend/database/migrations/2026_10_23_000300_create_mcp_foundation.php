<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Sanctum's standard credential structure; only hashes are stored.
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
        Schema::create('mcp_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('personal_access_token_id')->unique()->constrained();
            $table->foreignId('handling_office_id')->constrained('offices');
            $table->string('client_name', 100);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('mcp_tool_scopes', function (Blueprint $table) {
            $table->string('name', 80)->primary();
        });
        Schema::create('mcp_authorization_scopes', function (Blueprint $table) {
            $table->foreignId('authorization_id')->constrained('mcp_authorizations');
            $table->string('tool_scope', 80);
            $table->foreign('tool_scope')->references('name')->on('mcp_tool_scopes');
            $table->primary(['authorization_id', 'tool_scope']);
        });
        Schema::create('mcp_invocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('authorization_id')->constrained('mcp_authorizations');
            $table->string('tool_name', 80);
            $table->string('outcome', 20);
            $table->string('error_code', 40)->nullable();
            $table->foreignId('service_request_id')->nullable()->constrained();
            $table->foreignId('report_job_id')->nullable()->constrained('report_jobs');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->index(['authorization_id', 'started_at']);
        });
        // Historical migrations keep their own immutable initial catalog.
        DB::table('mcp_tool_scopes')->insert(array_map(fn ($name) => ['name' => $name], [
            'search_requests', 'get_request_details', 'get_personnel_workload',
            'get_feedback_metrics', 'get_report_job',
        ]));
    }

    public function down(): void
    {
        foreach (['mcp_invocations', 'mcp_authorization_scopes', 'mcp_tool_scopes', 'mcp_authorizations', 'personal_access_tokens'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
