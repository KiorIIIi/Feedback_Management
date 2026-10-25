<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Passport's authorization-code structures; password/device grants are not exposed.
        Schema::create('oauth_clients', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->nullableMorphs('owner'); $t->string('name');
            $t->string('secret')->nullable(); $t->string('provider')->nullable();
            $t->text('redirect_uris'); $t->text('grant_types'); $t->boolean('revoked'); $t->timestamps();
        });
        Schema::create('oauth_auth_codes', function (Blueprint $t) {
            $t->char('id', 80)->primary(); $t->foreignId('user_id')->constrained();
            $t->foreignUuid('client_id')->constrained('oauth_clients'); $t->text('scopes')->nullable();
            $t->boolean('revoked'); $t->dateTime('expires_at')->nullable();
        });
        Schema::create('oauth_access_tokens', function (Blueprint $t) {
            $t->char('id', 80)->primary(); $t->foreignId('user_id')->nullable()->constrained();
            $t->foreignUuid('client_id')->constrained('oauth_clients'); $t->string('name')->nullable();
            $t->text('scopes')->nullable(); $t->boolean('revoked'); $t->timestamps(); $t->dateTime('expires_at')->nullable();
        });
        Schema::create('oauth_refresh_tokens', function (Blueprint $t) {
            $t->char('id', 80)->primary(); $t->char('access_token_id', 80);
            $t->foreign('access_token_id')->references('id')->on('oauth_access_tokens');
            $t->boolean('revoked'); $t->dateTime('expires_at')->nullable();
        });
        Schema::create('assistant_settings', function (Blueprint $t) {
            $t->unsignedTinyInteger('id')->primary(); $t->boolean('mcp_enabled')->nullable();
            $t->boolean('assistant_enabled')->default(false); $t->text('provider_api_key')->nullable();
            $t->string('model', 100)->nullable(); $t->text('oauth_private_key')->nullable();
            $t->timestamp('provider_checked_at')->nullable(); $t->unsignedInteger('record_version')->default(1);
            $t->foreignId('updated_by')->nullable()->constrained('users'); $t->timestamp('updated_at')->nullable();
        });
        DB::table('assistant_settings')->insert(['id' => 1]);
        Schema::create('mcp_oauth_clients', function (Blueprint $t) {
            $t->foreignUuid('client_id')->primary()->constrained('oauth_clients');
            $t->foreignId('user_id')->constrained(); $t->foreignId('handling_office_id')->constrained('offices');
            $t->string('resource', 2048); $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('mcp_oauth_client_scopes', function (Blueprint $t) {
            $t->foreignUuid('client_id')->constrained('mcp_oauth_clients', 'client_id');
            $t->string('tool_scope', 80); $t->foreign('tool_scope')->references('name')->on('mcp_tool_scopes');
            $t->primary(['client_id', 'tool_scope']);
        });
        Schema::table('mcp_authorizations', function (Blueprint $t) {
            $t->foreignId('personal_access_token_id')->nullable()->change();
            $t->char('oauth_access_token_id', 80)->nullable()->unique();
            $t->foreign('oauth_access_token_id')->references('id')->on('oauth_access_tokens');
            $t->string('client_kind', 20)->default('pilot');
        });
        $condition = '(NEW.personal_access_token_id IS NULL) = (NEW.oauth_access_token_id IS NULL)';
        if (DB::getDriverName() === 'sqlite') {
            foreach (['INSERT', 'UPDATE'] as $operation) {
                DB::unprepared("CREATE TRIGGER mcp_credential_".strtolower($operation)." BEFORE $operation ON mcp_authorizations WHEN $condition BEGIN SELECT RAISE(ABORT, 'Exactly one MCP credential is required'); END");
            }
        } else {
            DB::statement('ALTER TABLE mcp_authorizations ADD CONSTRAINT mcp_one_credential CHECK ((personal_access_token_id IS NULL) <> (oauth_access_token_id IS NULL))');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS mcp_credential_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS mcp_credential_update');
        } else DB::statement('ALTER TABLE mcp_authorizations DROP CHECK mcp_one_credential');
        Schema::table('mcp_authorizations', function (Blueprint $t) {
            $t->dropForeign(['oauth_access_token_id']); $t->dropColumn(['oauth_access_token_id', 'client_kind']);
        });
        foreach (['mcp_oauth_client_scopes', 'mcp_oauth_clients', 'assistant_settings', 'oauth_refresh_tokens', 'oauth_access_tokens', 'oauth_auth_codes', 'oauth_clients'] as $table) Schema::dropIfExists($table);
    }
};
