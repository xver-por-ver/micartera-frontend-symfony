<?php

declare(strict_types=1);

namespace Tests\application;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Xver\MiCartera\Frontend\Symfony\Kernel;

/**
 * @internal
 */
#[CoversClass(Kernel::class)]
final class KernelTest extends TestCase
{
    public function testBootsWithAnAllowedEnvironment(): void
    {
        $kernel = new Kernel('test', false);

        try {
            $kernel->boot();
            self::assertSame('test', $kernel->getEnvironment());
        } finally {
            $kernel->shutdown();
        }
    }
}
