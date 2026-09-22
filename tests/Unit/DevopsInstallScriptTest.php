<?php

namespace Ggoitea\LaravelDevopsKit\Tests\Unit;

use Ggoitea\LaravelDevopsKit\Tests\TestCase;

class DevopsInstallScriptTest extends TestCase
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

    private string $target;

    protected function setUp(): void
    {
        parent::setUp();

        $this->target = sys_get_temp_dir() . '/devops-install-' . uniqid();
        mkdir($this->target, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->target);

        parent::tearDown();
    }

    public function test_script_publishes_all_files(): void
    {
        $this->runScript();

        foreach (self::EXPECTED_FILES as $file) {
            $this->assertFileExists($this->target . '/' . $file, "Missing {$file}");
        }

        $this->assertEquals(0755, fileperms($this->target . '/devops.sh') & 0777);
        $this->assertEquals(0755, fileperms($this->target . '/init.sh') & 0777);
    }

    public function test_script_does_not_overwrite_existing_files(): void
    {
        $target = $this->target . '/docker-compose.yml';

        file_put_contents($target, '# custom');

        $this->runScript();

        $this->assertSame('# custom', file_get_contents($target));
    }

    public function test_script_force_overwrites_existing_files(): void
    {
        $target = $this->target . '/docker-compose.yml';

        file_put_contents($target, '# custom');

        $this->runScript(['--force']);

        $this->assertNotSame('# custom', file_get_contents($target));
    }

    public function test_script_adds_uid_and_gid_to_existing_environment_files(): void
    {
        file_put_contents($this->target . '/.env.example', "APP_ENV=testing\n");
        file_put_contents($this->target . '/.env', 'APP_ENV=testing');

        $this->runScript();

        foreach (['.env.example', '.env'] as $file) {
            $content = file_get_contents($this->target . '/' . $file);

            $this->assertStringContainsString('UID=' . trim(shell_exec('id -u')), $content);
            $this->assertStringContainsString('GID=' . trim(shell_exec('id -g')), $content);
        }
    }

    public function test_script_preserves_existing_environment_values_without_duplicates(): void
    {
        file_put_contents($this->target . '/.env', "UID=2000\nGID=3000\n");

        $this->runScript(['--force']);

        $content = file_get_contents($this->target . '/.env');

        $this->assertStringContainsString('UID=2000', $content);
        $this->assertStringContainsString('GID=3000', $content);
        $this->assertSame(1, substr_count($content, 'UID='));
        $this->assertSame(1, substr_count($content, 'GID='));
    }

    public function test_script_does_not_create_missing_environment_files(): void
    {
        $this->runScript();

        $this->assertFileDoesNotExist($this->target . '/.env.example');
        $this->assertFileDoesNotExist($this->target . '/.env');
    }

    public function test_script_adds_override_compose_to_existing_gitignore(): void
    {
        file_put_contents($this->target . '/.gitignore', "vendor/\n");

        $this->runScript();
        $this->runScript();

        $content = file_get_contents($this->target . '/.gitignore');

        $this->assertStringContainsString("docker-compose.override.yml\n", $content);
        $this->assertSame(1, substr_count($content, 'docker-compose.override.yml'));
    }

    public function test_script_does_not_create_missing_gitignore(): void
    {
        $this->runScript();

        $this->assertFileDoesNotExist($this->target . '/.gitignore');
    }

    public function test_script_output_matches_stubs(): void
    {
        $this->runScript();

        foreach (self::EXPECTED_FILES as $file) {
            $this->assertFileEquals(
                $this->stubsDirectory() . '/' . $file,
                $this->target . '/' . $file,
                "{$file} difiere del stub"
            );
        }
    }

    public function test_script_standalone_output_matches_stubs(): void
    {
        $sandbox = sys_get_temp_dir() . '/devops-standalone-' . uniqid();
        mkdir($sandbox, 0755, true);

        try {
            copy($this->scriptPath(), $sandbox . '/devops-install.sh');
            chmod($sandbox . '/devops-install.sh', 0755);

            $target = $sandbox . '/out';
            mkdir($target, 0755, true);

            $command = sprintf(
                '%s %s 2>&1',
                escapeshellarg($sandbox . '/devops-install.sh'),
                escapeshellarg($target)
            );

            exec($command, $output, $exitCode);

            $this->assertSame(0, $exitCode, implode("\n", $output));

            foreach (self::EXPECTED_FILES as $file) {
                $this->assertFileEquals(
                    $this->stubsDirectory() . '/' . $file,
                    $target . '/' . $file,
                    "{$file} difiere del stub (modo autónomo)"
                );
            }
        } finally {
            $this->removeDirectory($sandbox);
        }
    }

    private function runScript(array $arguments = []): void
    {
        $command = sprintf(
            '%s %s %s 2>&1',
            escapeshellarg($this->scriptPath()),
            implode(' ', array_map('escapeshellarg', $arguments)),
            escapeshellarg($this->target)
        );

        exec($command, $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
    }

    private function scriptPath(): string
    {
        return dirname(__DIR__, 2) . '/bin/devops-install.sh';
    }

    private function stubsDirectory(): string
    {
        return dirname(__DIR__, 2) . '/resources/stubs';
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($directory);
    }
}
