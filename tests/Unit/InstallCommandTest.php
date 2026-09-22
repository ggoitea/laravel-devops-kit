<?php

namespace Ggoitea\LaravelDevopsKit\Tests\Unit;

use Ggoitea\LaravelDevopsKit\Tests\TestCase;

class InstallCommandTest extends TestCase
{
    private const EXPECTED_FILES = [
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

    public function test_install_command_adds_uid_and_gid_to_existing_environment_files(): void
    {
        file_put_contents(base_path('.env.example'), "APP_ENV=testing\n");
        file_put_contents(base_path('.env'), 'APP_ENV=testing');

        $this->artisan('devops:install')
            ->assertExitCode(0);

        foreach (['.env.example', '.env'] as $file) {
            $content = file_get_contents(base_path($file));

            $this->assertStringContainsString('UID=' . trim(shell_exec('id -u')), $content);
            $this->assertStringContainsString('GID=' . trim(shell_exec('id -g')), $content);
        }
    }

    public function test_install_command_preserves_existing_environment_values_without_duplicates(): void
    {
        file_put_contents(base_path('.env'), "UID=2000\nGID=3000\n");

        $this->artisan('devops:install --force')
            ->assertExitCode(0);

        $content = file_get_contents(base_path('.env'));

        $this->assertStringContainsString('UID=2000', $content);
        $this->assertStringContainsString('GID=3000', $content);
        $this->assertSame(1, substr_count($content, 'UID='));
        $this->assertSame(1, substr_count($content, 'GID='));
    }

    public function test_install_command_does_not_create_missing_environment_files(): void
    {
        $files = [base_path('.env.example'), base_path('.env')];
        $existing = [];

        foreach ($files as $file) {
            if (is_file($file)) {
                $existing[$file] = file_get_contents($file);
                unlink($file);
            }
        }

        try {
            $this->artisan('devops:install')
                ->assertExitCode(0);

            foreach ($files as $file) {
                $this->assertFileDoesNotExist($file);
            }
        } finally {
            foreach ($existing as $file => $content) {
                file_put_contents($file, $content);
            }
        }
    }

    public function test_install_command_adds_override_compose_to_existing_gitignore(): void
    {
        $file = base_path('.gitignore');
        $existing = is_file($file) ? file_get_contents($file) : null;
        file_put_contents($file, "vendor/\n");

        try {
            $this->artisan('devops:install')
                ->assertExitCode(0);

            $this->artisan('devops:install')
                ->assertExitCode(0);

            $content = file_get_contents($file);

            $this->assertStringContainsString("docker-compose.override.yml\n", $content);
            $this->assertSame(1, substr_count($content, 'docker-compose.override.yml'));
        } finally {
            if ($existing === null) {
                unlink($file);
            } else {
                file_put_contents($file, $existing);
            }
        }
    }

    public function test_install_command_does_not_create_missing_gitignore(): void
    {
        $file = base_path('.gitignore');
        $existing = is_file($file) ? file_get_contents($file) : null;

        if ($existing !== null) {
            unlink($file);
        }

        try {
            $this->artisan('devops:install')
                ->assertExitCode(0);

            $this->assertFileDoesNotExist($file);
        } finally {
            if ($existing !== null) {
                file_put_contents($file, $existing);
            }
        }
    }
}
