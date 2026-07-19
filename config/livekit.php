<?php

return [
  /*
  |--------------------------------------------------------------------------
  | LiveKit WebRTC (built-in browser camera streaming)
  |--------------------------------------------------------------------------
  | Video/audio flows through LiveKit — NOT stored on Laravel.
  | Run: docker compose -f docker-compose.livekit.yml up -d
  */
  'url' => env('LIVEKIT_URL', ''),
  'api_key' => env('LIVEKIT_API_KEY', 'devkey'),
  'api_secret' => env('LIVEKIT_API_SECRET', 'secret'),
  'token_ttl' => (int) env('LIVEKIT_TOKEN_TTL', 3600),
];
