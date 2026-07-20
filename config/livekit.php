<?php

return [
  /*
  |--------------------------------------------------------------------------
  | LiveKit WebRTC (built-in browser camera streaming)
  |--------------------------------------------------------------------------
  | Video/audio flows through LiveKit — NOT stored on Laravel.
  | From backend/: docker compose -f docker-compose.livekit.yml up -d
  | Or: powershell -File scripts/start-livekit.ps1
  */
  'url' => env('LIVEKIT_URL', ''),
  'api_key' => env('LIVEKIT_API_KEY', 'devkey'),
  'api_secret' => env('LIVEKIT_API_SECRET', 'secret'),
  'token_ttl' => (int) env('LIVEKIT_TOKEN_TTL', 3600),
];
