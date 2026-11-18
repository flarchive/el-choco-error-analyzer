<?php

use ElChoco\ErrorAnalyzer\AnalyzeController;
use Flarum\Extend;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),
    (new Extend\Routes('api'))
        ->get('/error-analyzer', 'elchoco.error-analyzer.scan', AnalyzeController::class),
    new Extend\Locales(__DIR__.'/locale'),
];
