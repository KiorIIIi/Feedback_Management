<?php

namespace App\Integrations\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;

class GetPersonnelWorkload extends ReadTool
{
    protected string $name = 'get_personnel_workload';
    protected string $description = 'Read active personnel, service eligibility and active-assignment counts in the authorized office, 20 per page. Physical availability is unknown; no availability records exist.';

    public function schema(JsonSchema $schema): array
    {
        return ['page' => $schema->integer()->min(1)->max(100000), 'service_type_id' => $schema->integer()->min(1)];
    }

    protected function rules(): array
    {
        return ['page' => 'sometimes|integer|min:1|max:100000', 'service_type_id' => 'sometimes|integer|min:1'];
    }
}
