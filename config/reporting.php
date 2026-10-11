<?php

return [
    'openai_api_key' => env('OPENAI_API_KEY'),
    'openai_model' => env('REPORTING_AI_MODEL', 'gpt-4o-mini'),
    // When no API key is set, AI suggest uses a deterministic local stub.
    'allow_stub' => env('REPORTING_AI_ALLOW_STUB', true),
];
