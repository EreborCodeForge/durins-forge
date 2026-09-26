<?php

declare(strict_types=1);

namespace App\Tooling\Doctor\Checks;

use App\Tooling\Doctor\Check;
use App\Tooling\Doctor\CheckResult;
use App\Tooling\Doctor\DoctorContext;
use App\Tooling\Doctor\DoctorExitCode;

final class PhpExtensionsCheck implements Check
{
    /** @var list<string> */
    private const array REQUIRED = ['json', 'pdo', 'msgpack', 'sockets'];

    public function id(): string
    {
        return 'php.extensions';
    }

    public function run(DoctorContext $context): array
    {
        $results = [];

        foreach (self::REQUIRED as $extension) {
            $id = 'php.ext.' . $extension;
            if (extension_loaded($extension)) {
                $results[] = CheckResult::ok($id, $extension, 'enabled');
            } else {
                $results[] = CheckResult::fail(
                    $id,
                    $extension,
                    'extension missing',
                    DoctorExitCode::MissingDependency,
                );
            }
        }

        if (extension_loaded('Zend OPcache') || extension_loaded('opcache')) {
            $results[] = CheckResult::ok('php.opcache', 'OPcache', 'enabled');
        } else {
            $results[] = CheckResult::warning('php.opcache', 'OPcache', 'not enabled in this process');
        }

        return $results;
    }
}
