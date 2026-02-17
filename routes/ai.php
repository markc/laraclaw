<?php

use App\Mcp\Servers\LaraClawServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', LaraClawServer::class)->middleware('auth:sanctum');
Mcp::local('laraclaw', LaraClawServer::class);
