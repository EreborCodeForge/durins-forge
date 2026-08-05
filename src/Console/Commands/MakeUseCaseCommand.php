<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Erebor\Mithril\Console\Command;
use Erebor\Mithril\Console\Color;

class MakeUseCaseCommand extends Command
{
    public static function getSignature(): string
    {
        return 'make:usecase';
    }

    public static function getDescription(): string
    {
        return 'Forges a new Use Case with its DTO and Interface';
    }

    public function execute(): int
    {
        $name = $this->args[0] ?? null;

        if (!$name) {
            $this->error("You must provide a name for the Use Case (e.g., User/CreateUser)");
            return 1;
        }

        // Normalize path (e.g., User/CreateUser -> User)
        $parts = explode('/', str_replace('\\', '/', $name));
        $className = array_pop($parts);
        $domain = implode('\\', $parts); // e.g., User
        $domainPath = implode('/', $parts);

        $baseDir = dirname(__DIR__, 2) . '/Application';

        // 1. Forge DTO
        $this->forgeDTO($baseDir, $domainPath, $domain, $className);

        // 2. Forge Interface
        $this->forgeInterface($baseDir, $domainPath, $domain, $className);

        // 3. Forge UseCase Implementation
        $this->forgeUseCase($baseDir, $domainPath, $domain, $className);

        $this->info("Reforged successfully! The artifacts are ready in src/Application.");
        return 0;
    }

    private function forgeDTO(string $baseDir, string $path, string $namespaceSuffix, string $name): void
    {
        $directory = "{$baseDir}/DTOs/{$path}";
        $this->ensureDirectoryExists($directory);

        $content = <<<PHP
<?php

declare(strict_types=1);

namespace App\Application\DTOs\\{$namespaceSuffix};

readonly class {$name}DTO
{
    public function __construct(
        // TODO: Add your properties here
    ) {}
}
PHP;
        
        $this->writeFile("{$directory}/{$name}DTO.php", $content);
    }

    private function forgeInterface(string $baseDir, string $path, string $namespaceSuffix, string $name): void
    {
        $directory = "{$baseDir}/UseCases/{$path}";
        $this->ensureDirectoryExists($directory);

        $content = <<<PHP
<?php

declare(strict_types=1);

namespace App\Application\UseCases\\{$namespaceSuffix};

use App\Application\DTOs\\{$namespaceSuffix}\\{$name}DTO;

interface {$name}UseCaseInterface
{
    public function execute({$name}DTO \$dto): mixed;
}
PHP;

        $this->writeFile("{$directory}/{$name}UseCaseInterface.php", $content);
    }

    private function forgeUseCase(string $baseDir, string $path, string $namespaceSuffix, string $name): void
    {
        $directory = "{$baseDir}/UseCases/{$path}";
        $this->ensureDirectoryExists($directory);

        $content = <<<PHP
<?php

declare(strict_types=1);

namespace App\Application\UseCases\\{$namespaceSuffix};

use App\Application\DTOs\\{$namespaceSuffix}\\{$name}DTO;

final class {$name}UseCase implements {$name}UseCaseInterface
{
    public function __construct(
        // private UserRepositoryInterface \$repository
    ) {}

    public function execute({$name}DTO \$dto): mixed
    {
        // TODO: Implement business logic
        return null;
    }
}
PHP;

        $this->writeFile("{$directory}/{$name}UseCase.php", $content);
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    private function writeFile(string $path, string $content): void
    {
        if (file_exists($path)) {
            $this->comment("File already exists: " . basename($path));
            return;
        }
        file_put_contents($path, $content);
        $this->info("Forged: " . basename($path));
    }

    protected function info(string $message): void
    {
        echo Color::green("  " . $message) . PHP_EOL;
    }

    protected function error(string $message): void
    {
        fwrite(STDERR, Color::red("  Error: " . $message) . PHP_EOL);
    }

    private function comment(string $message): void
    {
        echo Color::yellow("  " . $message) . PHP_EOL;
    }
}
