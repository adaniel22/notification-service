<?php

return [
    'host' => env('NATS_HOST', 'localhost'),
    'port' => (int)env('NATS_PORT', 4222),
];