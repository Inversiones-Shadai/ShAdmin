<?php

namespace Tests\Feature;

use App\Console\Commands\ServeShAdmin;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DevelopmentServerCacheTest extends TestCase
{
    public function test_artisan_uses_the_project_development_server(): void
    {
        $this->assertInstanceOf(ServeShAdmin::class, app(ServeCommand::class));
        $this->assertInstanceOf(ServeShAdmin::class, Artisan::all()['serve']);
    }

    public function test_windows_server_retains_opcache_with_an_isolated_cache_and_immediate_file_checks(): void
    {
        $command = new class extends ServeShAdmin
        {
            protected function host()
            {
                return '127.0.0.1';
            }

            protected function port()
            {
                return 8001;
            }

            public function arguments(): array
            {
                return $this->serverCommand();
            }
        };
        $arguments = $command->arguments();
        $this->assertContains('-S', $arguments);
        $this->assertContains('127.0.0.1:8001', $arguments);
        $this->assertNotContains('opcache.enable=0', $arguments);
        if (PHP_OS_FAMILY === 'Windows') {
            $this->assertContains('opcache.cache_id=ShAdmin-'.hash('sha256', base_path()), $arguments);
            $this->assertContains('opcache.validate_timestamps=1', $arguments);
            $this->assertContains('opcache.revalidate_freq=0', $arguments);
        }
    }
}
