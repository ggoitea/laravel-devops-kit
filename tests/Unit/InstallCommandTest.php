<?php

namespace Ggoitea\LaravelDevopsKit\Tests\Unit;

use Ggoitea\LaravelDevopsKit\Tests\TestCase;

class InstallCommandTest extends TestCase
{
    private const EXPECTED_FILES = [
        'Dockerfile',
        'docker-compose.yml',
        'Makefile',
        'devops.sh',
        'init.sh',
        'docker/Dockerfile.dev',
        'docker/Dockerfile.prod',
        'docker/nginx/default.conf',
        'docker/php/php.ini-development',
        'docker/php/php.ini-production',
        '.dockerignore',
    ];

    public function test_install_command_is_registered(): void
    {
        $this->artisan('devops:install --help')
            ->assertExitCode(0);
    }

    public function test_install_command_publishes_all_files(): void
    {
        $this->artisan('devops:install')
            ->assertExitCode(0);

        foreach (self::EXPECTED_FILES as $file) {
            $this->assertFileExists(base_path($file), "Missing {$file}");
        }

        $this->assertEquals(0755, fileperms(base_path('devops.sh')) & 0777);
        $this->assertEquals(0755, fileperms(base_path('init.sh')) & 0777);
    }

    public function test_install_command_does_not_overwrite_existing_files(): void
    {
        $target = base_path('docker-compose.yml');

        file_put_contents($target, '# custom');

        $this->artisan('devops:install')
            ->assertExitCode(0);

        $this->assertSame('# custom', file_get_contents($target));
    }

    public function test_install_command_force_overwrites_existing_files(): void
    {
        $target = base_path('docker-compose.yml');

        file_put_contents($target, '# custom');

        $this->artisan('devops:install --force')
            ->assertExitCode(0);

        $this->assertNotSame('# custom', file_get_contents($target));
    }
}
