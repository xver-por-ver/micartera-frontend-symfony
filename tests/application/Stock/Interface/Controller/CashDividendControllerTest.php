<?php

declare(strict_types=1);

namespace Tests\application\Stock\Interface\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Component\HttpFoundation\Response;
use Tests\application\ApplicationTestCase;
use Xver\MiCartera\Domain\Account\Application\Query\AccountQuery;
use Xver\MiCartera\Domain\Account\Infrastructure\Doctrine\AccountPersistence;
use Xver\PhpAuthCoreBundle\Auth\Application\AuthProvider;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Controller\CashDividendController;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Controller\StockAccountingController;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Controller\StockController;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Controller\StockPortfolioController;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Form\CashDividendType;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Form\NumericStringData;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Form\StockType;

#[CoversClass(CashDividendController::class)]
#[CoversClass(CashDividendType::class)]
#[UsesClass(NumericStringData::class)]
#[UsesClass(StockAccountingController::class)]
#[UsesClass(StockController::class)]
#[UsesClass(StockType::class)]
#[UsesClass(StockPortfolioController::class)]
class CashDividendControllerTest extends ApplicationTestCase
{
    public function testCreateCashDividendForPortfolioStock(): void
    {
        $this->client->loginUser(self::getAuthUser());
        $crawler = $this->client->request('GET', '/en_GB/stock');
        $crawler = $this->client->click($crawler->selectLink('Record dividend')->link());
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('cash_dividend_cmdSubmit')->form();
        $this->client->submit($form, [
            'cash_dividend[datetime]' => new \DateTime('yesterday', new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'cash_dividend[dividendPerShare]' => '0.25',
            'cash_dividend[expenses]' => '0',
        ]);

        self::assertResponseRedirects('/en_GB/stock', Response::HTTP_SEE_OTHER);
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

        $crawler = $this->client->request('GET', '/en_GB/stock/CABK');
        self::assertSelectorTextContains('#cash-dividends tbody tr', '0.25');
        self::assertSelectorTextContains('#cash-dividends tbody tr', '200');

        $crawler = $this->client->click($crawler->selectLink('Edit dividend')->link());
        $form = $crawler->selectButton('cash_dividend_cmdSubmit')->form();
        $this->client->submit($form, [
            'cash_dividend[dividendPerShare]' => '0.30',
            'cash_dividend[expenses]' => '0.50',
        ]);
        self::assertResponseRedirects('/en_GB/stock/CABK', Response::HTTP_SEE_OTHER);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('#cash-dividends tbody tr', '0.3');
        self::assertSelectorTextContains('#cash-dividends tbody tr', '0.5');

        $crawler = $this->client->click($crawler->selectLink('Edit dividend')->link());
        $invalidEditForm = $crawler->selectButton('cash_dividend_cmdSubmit')->form();
        $this->client->submit($invalidEditForm, [
            'cash_dividend[dividendPerShare]' => '0.30',
            'cash_dividend[expenses]' => '-0.50',
        ]);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.flash-error');

        $crawler = $this->client->request('GET', '/en_GB/stockaccounting');
        self::assertSelectorTextContains('#stock-accounting-summary', '60');
        self::assertSelectorTextContains('#stock-accounting-summary', '0.5');
        self::assertSelectorTextContains('#stock-accounting-summary', '59.5');
        self::assertSelectorExists('#accounting-year');
        self::assertSelectorExists('#accounting-event-tabs [role="tab"]');
        self::assertSelectorTextContains('#dividend-events tbody tr', 'CABK');
        self::assertSelectorTextContains('#dividend-events tbody tr', '0.3');
        self::assertSelectorTextContains('#dividend-events tbody tr', '60');
        self::assertSelectorExists('#dividend-events a[href$="/edit"]');
        self::assertSelectorExists('#dividend-events form.deleteForm');
        self::assertSelectorExists('#dividends-panel[hidden]');
        self::assertSelectorExists('#accounting-year-form input[name="view"][value="stocks"]');
        $selectedYear = $crawler->filter('#accounting-year option[selected]')->attr('value');
        $crawler = $this->client->request('GET', '/en_GB/stockaccounting?year=' . $selectedYear . '&view=dividends');
        self::assertSelectorExists('#dividends-tab[aria-selected="true"]');
        self::assertSelectorExists('#dividends-panel:not([hidden])');
        self::assertSelectorExists('#stocks-panel[hidden]');
        self::assertSelectorExists('form input[name="view"][value="dividends"]');
        $crawler = $this->client->click($crawler->filter('#dividend-events a[href$="/edit"]')->link());
        $editForm = $crawler->selectButton('cash_dividend_cmdSubmit')->form();
        $this->client->submit($editForm, [
            'cash_dividend[dividendPerShare]' => '0.30',
            'cash_dividend[expenses]' => '0.60',
        ]);
        self::assertResponseRedirects('/en_GB/stockaccounting?year=' . $selectedYear . '&view=dividends', Response::HTTP_SEE_OTHER);
        $crawler = $this->client->followRedirect();
        self::assertSelectorExists('#dividends-panel:not([hidden])');
        self::assertSelectorTextContains('#dividend-events tbody tr', '0.6');

        $crawler = $this->client->request('GET', '/en_GB/stock/CABK');
        $deleteForm = $crawler->filter('#cash-dividends form.deleteForm')->form();
        $values = $deleteForm->getValues();
        $values['_token'] = 'invalid';
        $deleteForm->setValues($values);
        $this->client->submit($deleteForm);
        self::assertResponseRedirects('/en_GB/stock/CABK', Response::HTTP_SEE_OTHER);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-error', self::$translator->trans('invalidFormToken'));
        self::assertSelectorExists('#cash-dividends tbody tr');

        $deleteForm = $crawler->filter('#cash-dividends form.deleteForm')->form();
        $this->client->submit($deleteForm);
        self::assertResponseRedirects('/en_GB/stock/CABK', Response::HTTP_SEE_OTHER);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-success', self::$translator->trans('actionCompletedSuccessfully'));
        self::assertSelectorTextContains('#cash-dividends tbody tr', self::$translator->trans('noRecordsFound'));

        $crawler = $this->client->request('GET', '/en_GB/stockportfolio');
        self::assertSelectorExists('a[href="/en_GB/cashdividend/new/CABK"]');

        $this->client->request('GET', '/en_GB/stock');
        self::assertSelectorExists('a[href="/en_GB/cashdividend/new/CABK"]');
    }

    public function testCreateFallsBackForInvalidAndExternalReferers(): void
    {
        $this->client->loginUser(self::getAuthUser());

        foreach ([
            ['http://example.com/en_GB/stock', '-4 days'],
            ['http://:80', '-3 days'],
        ] as [$referer, $date]) {
            $crawler = $this->client->request('GET', '/en_GB/stock');
            $crawler = $this->client->click($crawler->selectLink('Record dividend')->link());
            $form = $crawler->selectButton('cash_dividend_cmdSubmit')->form();
            $values = $form->getValues();
            $values['cash_dividend[refererPage]'] = $referer;
            $form->setValues($values);
            $this->client->submit($form, [
                'cash_dividend[datetime]' => new \DateTime($date, new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                'cash_dividend[dividendPerShare]' => '0.25',
                'cash_dividend[expenses]' => '0',
            ]);
            self::assertResponseRedirects('/en_GB/stockportfolio', Response::HTTP_SEE_OTHER);
        }
    }

    public function testCashDividendFormCanBeBuiltWithoutInitialData(): void
    {
        $this->client->loginUser(self::getAuthUser());
        $form = static::getContainer()->get('form.factory')->create(CashDividendType::class);

        self::assertSame('cash_dividend', $form->getName());
    }

    public function testRejectsDividendOwnedByAnotherAccount(): void
    {
        $this->client->loginUser(self::getAuthUser());
        $crawler = $this->client->request('GET', '/en_GB/stock');
        $crawler = $this->client->click($crawler->selectLink('Record dividend')->link());
        $form = $crawler->selectButton('cash_dividend_cmdSubmit')->form();
        $this->client->submit($form, [
            'cash_dividend[datetime]' => new \DateTime('-3 days', new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'cash_dividend[dividendPerShare]' => '0.25',
            'cash_dividend[expenses]' => '0',
        ]);

        $crawler = $this->client->request('GET', '/en_GB/stock/CABK');
        $editUrl = $crawler->selectLink('Edit dividend')->link()->getUri();
        $deleteForm = $crawler->filter('#cash-dividends form.deleteForm')->form();
        $deleteUrl = $deleteForm->getUri();
        $otherUser = new AuthProvider(new AccountQuery(new AccountPersistence(self::$registry)))
            ->loadUserByIdentifier('test_other@example.com');
        $this->client->loginUser($otherUser);

        $this->client->request('GET', $editUrl);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->client->request('DELETE', $deleteUrl);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testRejectsInvalidDividendIdsForEditAndDelete(): void
    {
        $this->client->loginUser(self::getAuthUser());

        $this->client->request('GET', '/en_GB/cashdividend/not-a-uuid/edit');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->client->request('DELETE', '/en_GB/cashdividend/not-a-uuid');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
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
