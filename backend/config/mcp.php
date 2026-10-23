<?php

return [
    // Explicit opt-in for a controlled, read-only pilot. No provider calls.
    'enabled' => (bool) env('MCP_ENABLED', false),
    'requests_per_minute' => 60,
];
