<?php

declare(strict_types=1);

namespace Tests\application;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\TestCaseTrait;
use Xver\SymfonyAuthBundle\Auth\Application\AuthProvider;
use Xver\SymfonyAuthBundle\Auth\Domain\AuthUser;
use Xver\MiCartera\Domain\Account\Application\Query\AccountQuery;
use Xver\MiCartera\Domain\Account\Infrastructure\Doctrine\AccountPersistence;

/**
 * @internal
 *
 * @coversNothing
 */
class ApplicationTestCase extends WebTestCase
{
    use TestCaseTrait;

    protected KernelBrowser $client;
    protected static TranslatorInterface $translator;
    protected static ?AuthUser $authUser;

    public static function setUpBeforeClass(): void
    {
        self::_setUpBeforeClass();
        self::$translator = static::getContainer()->get(TranslatorInterface::class);
    }

    public function setUp(): void
    {
        $this->_setUp();
        static::ensureKernelShutdown();

        $this->client = static::createClient(['environment' => 'test']);
    }

    public static function getAuthUser(): AuthUser
    {
        self::$authUser ?? self::$authUser = new AuthProvider(new AccountQuery(new AccountPersistence(self::$registry)))->loadUserByIdentifier('test@example.com');

        return self::$authUser;
    }
}
