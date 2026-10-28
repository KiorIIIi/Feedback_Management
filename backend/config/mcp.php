<?php

$enabled = filter_var(env('MCP_ENABLED', false), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
if ($enabled === null) {
    throw new RuntimeException('Invalid MCP_ENABLED. Use true or false.');
}

return [
    // Explicit opt-in for a controlled, read-only pilot. No provider calls.
    'enabled' => $enabled,
    'admin_setup_allowed' => filter_var(env('MCP_ADMIN_SETUP_ALLOWED', in_array(env('APP_ENV'), ['local', 'testing'], true)), FILTER_VALIDATE_BOOL),
    'requests_per_minute' => 60,
];
