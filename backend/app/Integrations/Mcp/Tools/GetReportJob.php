<?php

namespace App\Integrations\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;

class GetReportJob extends ReadTool
{
    protected string $name = 'get_report_job';
    protected string $description = 'Read a report job owned by the authenticated administrator and authorized office. Pending/processing/failed exports are not ready. A successful existing private file can be downloaded through an authenticated portal browser session.';

    public function schema(JsonSchema $schema): array
    {
        return ['report_job_id' => $schema->integer()->min(1)->required()];
    }

    protected function rules(): array
    {
        return ['report_job_id' => 'required|integer|min:1'];
    }
}
