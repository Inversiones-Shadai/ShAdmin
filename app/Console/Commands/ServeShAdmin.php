<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ServeCommand;

class ServeShAdmin extends ServeCommand
{
    /** @return list<string> */
    protected function serverCommand(): array
    {
        $command = parent::serverCommand();

        if (PHP_OS_FAMILY === 'Windows') {
            // Keep this project's bytecode separate from the API and HQ processes.
            array_splice($command, 1, 0, [
                '-d', 'opcache.cache_id=ShAdmin-'.hash('sha256', base_path()),
                '-d', 'opcache.validate_timestamps=1',
                '-d', 'opcache.revalidate_freq=0',
            ]);
        }

        return array_values($command);
    }
}
