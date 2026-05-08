<?php

declare(strict_types=1);

// Dev 2: CV Scan - external parser settings used before the local resume scanner fallback.
return [
    'enabled' => true,
    'endpoint' => 'https://cvparser.ai/api/v4/parse',
    'api_keys' => [
        'cf518d9277ce17d44502c1bd396f17ae',
        
    ],
    'connect_timeout' => 3,
    'timeout' => 8,
];
