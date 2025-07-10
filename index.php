<?php

use Kirby\Cms\App as Kirby;

require_once __DIR__ . '/vendor/autoload.php';

Kirby::plugin('getkirby/base', extends: [
    'commands' => [
        'table:create' => require __DIR__ . '/commands/table/create.php'
    ]
]);
