<?php

namespace Ggoitea\LaravelDevopsKit\Console\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'devops:install {--force : Sobrescribir archivos existentes}';

    protected $description = 'Instala los archivos Docker y Makefile del proyecto';

    private const FILES = [
        'docker-compose.yml' => 'docker-compose.yml',
        'Makefile' => 'Makefile',
        'devops.sh' => 'devops.sh',
        'init.sh' => 'init.sh',
        'docker/Dockerfile.dev' => 'docker/Dockerfile.dev',
        'docker/Dockerfile.prod' => 'docker/Dockerfile.prod',
        'docker/nginx/default.conf' => 'docker/nginx/default.conf',
        'docker/php/php.ini-development' => 'docker/php/php.ini-development',
        'docker/php/php.ini-production' => 'docker/php/php.ini-production',
        '.dockerignore' => '.dockerignore',
    ];

    public function handle(): int
    {
        foreach (self::FILES as $stub => $destination) {
            if (! $this->installFile($stub, $destination)) {
                return self::FAILURE;
            }
        }

        if (! $this->ensureEnvironmentVariables()) {
            return self::FAILURE;
        }

        if (! $this->ensureGitignoreEntry()) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function installFile(string $stub, string $destination): bool
    {
        $source = __DIR__ . '/../../../resources/stubs/' . $stub;
        $target = base_path($destination);

        if (! $this->ensureDirectory(dirname($target))) {
            return false;
        }

        if (is_file($target) && ! $this->option('force')) {
            $this->warn("Omitido: {$destination} ya existe. Usa --force para reemplazarlo.");

            return true;
        }

        if (! copy($source, $target)) {
            $this->error("No se pudo instalar: {$destination}");

            return false;
        }

        if (in_array($stub, ['devops.sh', 'init.sh'], true)) {
            chmod($target, 0755);
        }

        $this->info("Instalado: {$destination}");

        return true;
    }

    private function ensureDirectory(string $directory): bool
    {
        if (is_dir($directory)) {
            return true;
        }

        if (! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->error("No se pudo crear el directorio: {$directory}");

            return false;
        }

        return true;
    }

    private function ensureEnvironmentVariables(): bool
    {
        $uid = $this->currentUserId('posix_getuid');
        $gid = $this->currentUserId('posix_getgid');

        if ($uid === null || $gid === null) {
            $this->error('No se pudieron determinar UID y GID del usuario actual.');

            return false;
        }

        foreach ([base_path('.env.example'), base_path('.env')] as $file) {
            if (! $this->addEnvironmentVariables($file, $uid, $gid)) {
                return false;
            }
        }

        return true;
    }

    private function addEnvironmentVariables(string $file, string $uid, string $gid): bool
    {
        if (! is_file($file)) {
            return true;
        }

        $content = file_get_contents($file);

        if ($content === false) {
            $this->error("No se pudo leer: {$file}");

            return false;
        }

        $missing = [];

        foreach (['UID' => $uid, 'GID' => $gid] as $variable => $value) {
            if (preg_match('/^' . preg_quote($variable, '/') . '=/m', $content) !== 1) {
                $missing[] = "{$variable}={$value}";
            }
        }

        if ($missing === []) {
            return true;
        }

        $suffix = $content !== '' && ! str_ends_with($content, "\n") ? "\n" : '';
        $updated = $content . $suffix . implode("\n", $missing) . "\n";

        if (file_put_contents($file, $updated, LOCK_EX) === false) {
            $this->error("No se pudo actualizar: {$file}");

            return false;
        }

        return true;
    }

    private function currentUserId(string $function): ?string
    {
        if (function_exists($function)) {
            return (string) $function();
        }

        $command = $function === 'posix_getgid' ? 'id -g' : 'id -u';
        $value = trim((string) shell_exec($command));

        return ctype_digit($value) ? $value : null;
    }

    private function ensureGitignoreEntry(): bool
    {
        $file = base_path('.gitignore');

        if (! is_file($file)) {
            return true;
        }

        $content = file_get_contents($file);

        if ($content === false) {
            $this->error("No se pudo leer: {$file}");

            return false;
        }

        if (preg_match('/^docker-compose\.override\.yml$/m', $content) === 1) {
            return true;
        }

        $suffix = $content !== '' && ! str_ends_with($content, "\n") ? "\n" : '';

        if (file_put_contents($file, $content . $suffix . "docker-compose.override.yml\n", LOCK_EX) === false) {
            $this->error("No se pudo actualizar: {$file}");

            return false;
        }

        return true;
    }
}
