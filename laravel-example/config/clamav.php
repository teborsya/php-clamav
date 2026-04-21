<?php

return [
    'enabled' => env('CLAMAV_ENABLED', true),
    'binary' => env('CLAMAV_BINARY', '/usr/bin/clamdscan'),
    'args' => array_filter(explode(' ', env('CLAMAV_ARGS', '--no-summary'))),
    'fail_closed' => env('CLAMAV_FAIL_CLOSED', true),
];
