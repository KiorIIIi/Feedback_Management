<?php
namespace App\Integrations\Mcp;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AssistantSettings
{
    public function row(): object { return DB::table('assistant_settings')->find(1); }

    public function editable(): bool { return config('mcp.admin_setup_allowed') === true; }

    public function syntheticEnvironment(): bool
    {
        if (!app()->environment(['local', 'testing']) || !config('security.demo_seed')) return false;
        $db = DB::connection()->getConfig(); $driver = $db['driver'] ?? '';
        if ($driver === 'mysql') return ($db['host'] ?? '') === '127.0.0.1' && (int) ($db['port'] ?? 0) === 3307
            && ($db['database'] ?? '') === (app()->environment('testing') ? 'clsu_feedback_test' : 'clsu_feedback');
        if (!app()->environment('testing') || $driver !== 'sqlite') return false;
        if (($db['database'] ?? '') === ':memory:') return true;
        $resolved = realpath($db['database'] ?? '');
        if (!$resolved) return false;
        $path = str_replace('\\', '/', $resolved);
        $testRoot = realpath(base_path('../.local/e2e'));
        return $testRoot && str_starts_with($path, rtrim(str_replace('\\', '/', $testRoot), '/').'/');
    }

    public function enabled(): bool
    {
        if (!$this->editable()) return config('mcp.enabled') === true;
        $choice = $this->row()->mcp_enabled;
        return $choice === null ? config('mcp.enabled') === true : in_array($choice, [true, 1, '1'], true);
    }

    public function confirm(User $user, string $password): void
    {
        app(McpAuthorization::class)->assertAdministrator($user);
        abort_unless($this->editable(), 403, 'Your hosting operator must allow admin integration setup for this environment.');
        if (!Hash::check($password, $user->password)) throw ValidationException::withMessages(['password' => 'Your current password is incorrect.']);
    }

    public function status(User $user): array
    {
        app(McpAuthorization::class)->assertAdministrator($user);
        $row = $this->row();
        $last = DB::table('mcp_invocations')->join('mcp_authorizations', 'mcp_authorizations.id', '=', 'mcp_invocations.authorization_id')
            ->where('mcp_authorizations.user_id', $user->id)->whereIn('client_kind', ['pilot', 'oauth'])
            ->where('outcome', 'successful')->orderByDesc('finished_at')->value('finished_at');
        return ['record_version' => $row->record_version, 'editable' => $this->editable(),
            'mcp_enabled' => $this->enabled(), 'endpoint' => rtrim(config('app.url'), '/').'/api/mcp',
            'remote_ready' => $this->enabled() && str_starts_with(config('app.url'), 'https://'),
            'assistant_available' => false, 'provider_calls_enabled' => false, 'data_mode' => 'local_only',
            'local_tools_available' => $this->syntheticEnvironment() && str_ends_with($user->email, '@clsu.demo'),
            'assistant_enabled' => false, 'key_configured' => $row->provider_api_key !== null,
            'model' => $row->model ?? '', 'provider_checked_at' => $row->provider_checked_at,
            'last_external_call_at' => $last, 'tools' => McpAuthorization::TOOLS];
    }

    public function save(User $user, array $data): array
    {
        $this->confirm($user, $data['password']);
        if ($data['assistant_enabled']) throw ValidationException::withMessages(['assistant_enabled' => 'Provider calls are disabled by the current local-only data policy.']);
        DB::transaction(function () use ($user, $data) {
            $row = DB::table('assistant_settings')->where('id', 1)->lockForUpdate()->first();
            abort_unless((int) $row->record_version === $data['expected_version'], 409, 'Integration settings changed. Reload and review the latest settings.');
            $key = ($data['remove_key'] ?? false) ? null : $row->provider_api_key;
            if (!empty($data['api_key'])) $key = Crypt::encryptString($data['api_key']);
            if ($data['assistant_enabled'] && (!$key || empty($data['model']))) throw ValidationException::withMessages(['assistant_enabled' => 'Add an API key and model before enabling the portal assistant.']);
            $private = $row->oauth_private_key;
            if ($data['mcp_enabled'] && !$private) {
                $bundledConfig = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
                if (PHP_OS_FAMILY === 'Windows' && is_file($bundledConfig)) \phpseclib4\Crypt\RSA::setOpenSSLConfigPath($bundledConfig);
                $rsa = \phpseclib4\Crypt\RSA::createKey(2048);
                $private = Crypt::encryptString((string) $rsa);
            }
            DB::table('assistant_settings')->where('id', 1)->update([
                'mcp_enabled' => $data['mcp_enabled'], 'assistant_enabled' => $data['assistant_enabled'],
                'provider_api_key' => $key, 'model' => $data['model'] ?: null, 'oauth_private_key' => $private,
                'provider_checked_at' => ($key === $row->provider_api_key && $data['model'] === ($row->model ?? '')) ? $row->provider_checked_at : null,
                'record_version' => $row->record_version + 1, 'updated_by' => $user->id, 'updated_at' => now(),
            ]);
            $this->audit($user, 'assistant.settings_updated', ['mcp_enabled' => $data['mcp_enabled'], 'assistant_enabled' => $data['assistant_enabled'], 'key_configured' => $key !== null]);
        });
        return $this->status($user);
    }

    public function configureOAuth(): void
    {
        $encrypted = $this->row()->oauth_private_key;
        abort_unless($encrypted, 503, 'Enable MCP in admin setup first.');
        $private = Crypt::decryptString($encrypted);
        $details = openssl_pkey_get_details(openssl_pkey_get_private($private));
        config(['passport.private_key' => $private, 'passport.public_key' => $details['key']]);
        \Illuminate\Support\Facades\URL::forceRootUrl(rtrim(config('app.url'), '/'));
    }

    public function audit(User $user, string $action, array $facts): void
    {
        DB::table('audit_entries')->insert(['actor_user_id' => $user->id, 'actor_kind' => 'human', 'action' => $action, 'after' => json_encode($facts, JSON_THROW_ON_ERROR), 'occurred_at' => now()]);
    }
}
