<?php

use App\Services\Agent\Commands\HelpCommand;
use App\Services\Agent\Commands\InfoCommand;
use App\Services\Agent\Commands\ModelCommand;
use App\Services\Agent\Commands\NewCommand;
use App\Services\Agent\Commands\RenameCommand;

return [

    /*
    |--------------------------------------------------------------------------
    | Command Handlers
    |--------------------------------------------------------------------------
    |
    | Map of command names to handler classes. Aliases are resolved automatically
    | from each handler's aliases() method.
    |
    */

    'commands' => [
        'model' => ModelCommand::class,
        'rename' => RenameCommand::class,
        'help' => HelpCommand::class,
        'info' => InfoCommand::class,
        'new' => NewCommand::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Heuristic Thresholds
    |--------------------------------------------------------------------------
    */

    'query_max_length' => 200,
    'task_min_length' => 500,

];
