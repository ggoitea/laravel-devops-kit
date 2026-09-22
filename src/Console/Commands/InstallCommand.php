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
}
