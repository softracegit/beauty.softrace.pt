<?php

return [

  'enabled' => env('AI_ASSISTANT_ENABLED', true),

  /*
  |--------------------------------------------------------------------------
  | API compatível com OpenAI (Groq, OpenRouter, etc.)
  |--------------------------------------------------------------------------
  */
  'api_key' => env('AI_ASSISTANT_API_KEY'),

  'base_url' => rtrim(env('AI_ASSISTANT_BASE_URL', 'https://api.groq.com/openai/v1'), '/'),

  'model' => env('AI_ASSISTANT_MODEL', 'llama-3.3-70b-versatile'),

  'timeout_seconds' => (int) env('AI_ASSISTANT_TIMEOUT', 60),

  'max_history_messages' => (int) env('AI_ASSISTANT_MAX_HISTORY', 12),

];
