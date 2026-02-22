<?php

namespace App\Events;

use App\Models\Memory;
use Illuminate\Foundation\Events\Dispatchable;

class MemorySaved
{
    use Dispatchable;

    public function __construct(
        public Memory $memory,
    ) {}
}
