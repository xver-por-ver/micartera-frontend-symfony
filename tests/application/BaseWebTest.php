<?php

declare(strict_types=1);

namespace Tests\application;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;
use Xver\MiCartera\Frontend\Symfony\Account\Interface\Controller\AccountController;
use Xver\MiCartera\Frontend\Symfony\Account\Interface\Form\RegistrationFormType;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Controller\StockAccountingController;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Controller\StockController;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Controller\StockOperateController;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Controller\StockPortfolioController;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Form\StockOperateImportType;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Form\StockType;

/**
 * @internal
 */
#[CoversClass(StockAccountingController::class)]
#[CoversClass(StockPortfolioController::class)]
#[CoversClass(StockController::class)]
#[CoversClass(AccountController::class)]
#[CoversClass(StockType::class)]
#[CoversClass(RegistrationFormType::class)]
#[UsesClass(StockOperateController::class)]
#[UsesClass(StockOperateImportType::class)]
class BaseWebTest extends ApplicationTestCase
{
    private static array $pages = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        /** @var Router */
        $router = static::getContainer()->get('router');
        self::configurePages($router);
    }

    private static function skipRoute(Route $route): bool
    {
        return
            false !== strpos($route->getPath(), '{id}')
            || false !== strpos($route->getPath(), '{type}')
            || false !== strpos($route->getPath(), '{stock}');
    }

    private static function configurePages(Router $router): void
    {
        foreach ($router->getRouteCollection() as $controller_method_name => $route) {
            if ('Xver\MiCartera\Frontend\Symfony\Account\Interface\Controller\AccountController::index' === $route->getDefault('_controller')) {
                continue;
            }
            if (self::skipRoute($route)) {
                continue;
            }
            if ('/healthz' === $route->getPath()) {
                self::$pages[] = [
                    'page' => $router->generate($controller_method_name, []),
                    'locale' => 'en_GB',
                    'public' => true,
                    'redirect' => false,
                ];
                continue;
            }
            $locales = explode('|', $route->getRequirement('_locale'));
            foreach ($locales as $locale) {
                $parameters = ['_locale' => $locale];
                $redirect = false;
                if ('Xver\MiCartera\Frontend\Symfony\Account\Interface\Controller\AccountController::logout' === $route->getDefault('_controller')) {
                    $redirect = $router->generate('app_login', $parameters);
                }
                self::$pages[] = [
                    'page' => $router->generate($controller_method_name, $parameters),
                    'locale' => $locale,
                    'public'
                        => in_array(
                            $route->getDefault('_controller'),
                            [
                                'Xver\MiCartera\Frontend\Symfony\Account\Interface\Controller\AccountController::login',
                                'Xver\MiCartera\Frontend\Symfony\Account\Interface\Controller\AccountController::register',
                                'Xver\MiCartera\Frontend\Symfony\Account\Interface\Controller\AccountController::termsConditions',
                            ]
                        ),
                    'redirect' => $redirect,
                ];
            }
        }
    }

    public function testPagesRedirectToPortfolioWhenAccessedWhileLoggedIn(): void
    {
        $this->client->loginUser(self::getAuthUser());
        $crawler = $this->client->request('GET', '/');
        $this->assertResponseRedirects('/en_GB/stockportfolio', Response::HTTP_FOUND);

        $crawler = $this->client->request('GET', '/en_GB/login');
        $this->assertResponseRedirects('/en_GB/stockportfolio', Response::HTTP_FOUND);

        $crawler = $this->client->request('GET', '/en_GB/register');
        $this->assertResponseRedirects('/en_GB/stockportfolio', Response::HTTP_FOUND);
    }

    public function testRedirectsToLoginIfNotAuthenticated(): void
    {
        foreach (self::$pages as $page) {
            if (!$page['public']) {
                $crawler = $this->client->request('GET', $page['page']);
                $this->assertResponseRedirects('http://localhost/' . $page['locale'] . '/login', Response::HTTP_FOUND);
            }
        }
    }

    public function testNonPublicSuccessfulResponse(): void
    {
        $this->client->loginUser(self::getAuthUser());

        foreach (self::$pages as $page) {
            if (!$page['public'] && !$page['redirect']) {
                $crawler = $this->client->request('GET', $page['page']);
                $this->assertResponseIsSuccessful('Requested page: ' . $page['page']);
            }
        }
    }

    public function testPublicSuccessfulResponse(): void
    {
        foreach (self::$pages as $page) {
            if ($page['public']) {
                $crawler = $this->client->request('GET', $page['page']);
                $this->assertResponseIsSuccessful();
            }
        }
    }

    public function testRedirectResponses(): void
    {
        foreach (self::$pages as $page) {
            if ($page['redirect']) {
                $crawler = $this->client->request('GET', $page['page']);
                $this->assertResponseRedirects('http://localhost' . $page['redirect'], Response::HTTP_FOUND);
            }
        }
    }
}
