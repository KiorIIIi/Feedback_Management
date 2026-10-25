<?php

namespace App\Integrations\Mcp\Tools;

use App\Integrations\Mcp\McpAuthorization;
use App\Integrations\Mcp\McpReadService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

abstract class ReadTool extends Tool
{
    abstract protected function rules(): array;

    public function validationRules(): array { return $this->rules(); }

    public function shouldRegister(): bool
    {
        $grant = request()->attributes->get('mcp_authorization');
        return app(\App\Integrations\Mcp\AssistantSettings::class)->enabled() && $grant && app(McpAuthorization::class)->permits($grant, $this->name());
    }

    public function toArray(): array
    {
        $data = parent::toArray();
        $data['annotations'] = array_merge($data['annotations'], [
            'readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false,
        ]);
        $data['inputSchema']['additionalProperties'] = false;
        return $data;
    }

    public function handle(Request $request): Response
    {
        try {
            abort_unless($this->shouldRegister(), 403);
            $rules = $this->rules();
            if (array_diff(array_keys($request->all()), array_keys($rules))) {
                throw ValidationException::withMessages(['arguments' => 'Unknown arguments are not permitted.']);
            }
            $input = $request->validate($rules);
            $grant = request()->attributes->get('mcp_authorization');
            $data = app(McpReadService::class)->execute($this->name(), $grant->user, $input);
            // Only successful, authorized targets are retained as typed audit references.
            if ($this->name() === 'get_request_details') request()->attributes->set('mcp_request_id', $input['request_id']);
            if ($this->name() === 'get_report_job') request()->attributes->set('mcp_report_job_id', $input['report_job_id']);
            return Response::json(['state' => 'successful', 'data' => $data]);
        } catch (ValidationException $exception) {
            return $this->failure('invalid_input', 'Review the tool arguments.', $exception->errors());
        } catch (ModelNotFoundException $exception) {
            return $this->failure('not_found', 'The record is unavailable within your authorized scope.');
        } catch (HttpExceptionInterface $exception) {
            $code = $exception->getStatusCode() === 404 ? 'not_found' : 'permission_denied';
            return $this->failure($code, 'The record is unavailable within your authorized scope.');
        } catch (Throwable $exception) {
            // Do not log arguments, bearer credentials, SQL bindings, or record content.
            Log::error('MCP read failed.', ['exception_class' => $exception::class]);
            return $this->failure('temporary_failure', 'The read could not be completed. Retry later.');
        }
    }

    private function failure(string $code, string $message, array $errors = []): Response
    {
        request()->attributes->set('mcp_error_code', $code);
        return Response::error(json_encode(['state' => 'failed', 'error' => ['code' => $code, 'message' => $message, 'fields' => $errors]], JSON_THROW_ON_ERROR));
    }
}
