<?php

namespace App\Integrations\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;

class GetFeedbackMetrics extends ReadTool
{
    protected string $name = 'get_feedback_metrics';
    protected string $description = 'Read backend-calculated service metrics and verified evaluation sample counts for 1–366 calendar days (default 30). General public feedback remains separate. Returns period and denominator definitions.';

    public function schema(JsonSchema $schema): array
    {
        return ['days' => $schema->integer()->min(1)->max(366)];
    }

    protected function rules(): array
    {
        return ['days' => 'sometimes|integer|min:1|max:366'];
    }
}
