<?php

namespace App\Integrations\Mcp;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class McpInvocationAudit
{
    public function finish(int $id, array $result): void
    {
        $updated = DB::table('mcp_invocations')->where('id', $id)
            ->where('outcome', 'processing')->whereNull('finished_at')->update($result);
        if ($updated !== 1) {
            // Never return record content when durable final audit evidence is unavailable.
            throw new \RuntimeException('MCP invocation could not be finalized.');
        }
    }

    public function reconcile(CarbonInterface $before): int
    {
        return DB::table('mcp_invocations')->where('outcome', 'processing')
            ->whereNull('finished_at')->where('started_at', '<', $before)->update([
                'outcome' => 'failed', 'error_code' => 'interrupted_unknown', 'finished_at' => now(),
            ]);
    }
}
