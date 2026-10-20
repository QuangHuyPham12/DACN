<?php

return [
    // Without a key, the assistant uses the local catalog and explicit store rules.
    'api_key' => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_MODEL', 'gpt-4.1-mini'),
    'timeout' => 15,
    'history_limit' => 12,
];
