<?php

namespace Ggoitea\LaravelDevopsKit\Tests\Unit;

use Ggoitea\LaravelDevopsKit\Tests\TestCase;

class DevopsInstallScriptTest extends TestCase
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