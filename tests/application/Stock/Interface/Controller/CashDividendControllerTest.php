<?php

declare(strict_types=1);

namespace Tests\application\Stock\Interface\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Component\HttpFoundation\Response;
use Tests\application\ApplicationTestCase;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Controller\CashDividendController;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Controller\StockController;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Controller\StockPortfolioController;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Form\CashDividendType;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Form\NumericStringData;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Form\StockType;

#[CoversClass(CashDividendController::class)]
#[CoversClass(CashDividendType::class)]
#[UsesClass(NumericStringData::class)]
#[UsesClass(StockController::class)]
#[UsesClass(StockType::class)]
#[UsesClass(StockPortfolioController::class)]
class CashDividendControllerTest extends ApplicationTestCase
{
    public function testCreateCashDividendForPortfolioStock(): void
    {
        $this->client->loginUser(self::getAuthUser());
        $crawler = $this->client->request('GET', '/en_GB/cashdividend/new/CABK');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('cash_dividend_cmdSubmit')->form();
        $this->client->submit($form, [
            'cash_dividend[datetime]' => new \DateTime('yesterday', new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'cash_dividend[dividendPerShare]' => '0.25',
            'cash_dividend[expenses]' => '0',
        ]);

        self::assertResponseRedirects('/en_GB/stockportfolio', Response::HTTP_SEE_OTHER);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-success', self::$translator->trans('actionCompletedSuccessfully'));

        $crawler = $this->client->request('GET', '/en_GB/cashdividend/new/CABK');
        $form = $crawler->selectButton('cash_dividend_cmdSubmit')->form();
        $this->client->submit($form, [
            'cash_dividend[datetime]' => new \DateTime('yesterday', new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'cash_dividend[dividendPerShare]' => '0.25',
            'cash_dividend[expenses]' => '0',
        ]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(
            '.flash-error',
            self::$translator->trans('cashDividendExistsOnDateTime', [], 'MiCarteraDomain')
        );

        $this->client->request('GET', '/en_GB/stock/CABK');
        self::assertSelectorTextContains('#cash-dividends tbody tr', '0.25');
        self::assertSelectorTextContains('#cash-dividends tbody tr', '200');

        $crawler = $this->client->request('GET', '/en_GB/stockportfolio');
        self::assertSelectorExists('a[href="/en_GB/cashdividend/new/CABK"]');

        $this->client->request('GET', '/en_GB/stock');
        self::assertSelectorExists('a[href="/en_GB/cashdividend/new/CABK"]');
    }

    public function testShowsHelpfulErrorWhenAccountHadNoHoldingOnDividendDate(): void
    {
        $this->client->loginUser(self::getAuthUser());
        $crawler = $this->client->request('GET', '/en_GB/cashdividend/new/ROVI');
        $form = $crawler->selectButton('cash_dividend_cmdSubmit')->form();
        $this->client->submit($form, [
            'cash_dividend[datetime]' => new \DateTime('yesterday', new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'cash_dividend[dividendPerShare]' => '0.25',
            'cash_dividend[expenses]' => '0',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(
            '.flash-error',
            self::$translator->trans('dividendRequiresPositiveHolding', [], 'MiCarteraDomain')
        );
    }
}
