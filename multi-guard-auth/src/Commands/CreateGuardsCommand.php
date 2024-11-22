<?php

namespace Bro\MultiGuardAuth\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CreateGuardsCommand extends Command
{
    protected $signature = 'create:guards';
    protected $description = 'Create multiple authentication guards';

    public function handle()
    {
        // Your existing handle method code...
    }

    // Your other protected methods...
}