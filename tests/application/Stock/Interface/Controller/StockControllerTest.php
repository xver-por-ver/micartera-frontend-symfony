<?php

declare(strict_types=1);

namespace Tests\application\Stock\Interface\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Component\HttpFoundation\Response;
use Tests\application\ApplicationTestCase;
use Xver\MiCartera\Domain\Stock\Domain\StockPriceVO;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Controller\StockController;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Form\NumericStringData;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Form\StockType;

/**
 * @internal
 */
#[CoversClass(StockController::class)]
#[CoversClass(StockType::class)]
#[UsesClass(NumericStringData::class)]
class StockControllerTest extends ApplicationTestCase
{
    public function testNewStock(): void
    {
        $this->client->loginUser(self::getAuthUser());
        $crawler = $this->client->request('GET', '/en_GB/stock/form-new');

        // select the button
        $buttonCrawlerNode = $crawler->selectButton('stock_cmdSubmit');

        // retrieve the Form object for the form belonging to this button
        $form = $buttonCrawlerNode->form();
        $formName = $form->getName();

        // set values on a form object
        $formFields = [
            $formName . '[code]' => 'ABCD',
            $formName . '[name]' => 'ABCD Name',
            $formName . '[price]' => '6.5467',
            $formName . '[exchange]' => 'MCE',
        ];

        // test new
        $this->client->submit($form, $formFields);
        $this->assertResponseRedirects('/en_GB/stock', Response::HTTP_SEE_OTHER);
        $crawler = $this->client->followRedirect();
        $this->assertSelectorTextContains('.flash-success', self::$translator->trans('actionCompletedSuccessfully'));

        // test domain exception
        $this->client->submit($form, $formFields);
        $this->assertRouteSame('stock_new');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.flash-error', self::$translator->trans('stockExists', [], 'MiCarteraDomain'));
    }

    #[Depends('testNewStock')]
    public function testUpdateStock(): void
    {
        $this->client->loginUser(self::getAuthUser());
        $crawler = $this->client->request('GET', '/en_GB/stock/ABCD');

        // select the button
        $buttonCrawlerNode = $crawler->selectButton('stock_cmdSubmit');

        // retrieve the Form object for the form belonging to this button
        $form = $buttonCrawlerNode->form();
        $formName = $form->getName();

        // set values on a form object
        $formFields = [
            $formName . '[name]' => 'ABCD New Name',
            $formName . '[price]' => '6.5467',
            $formName . '[refererPage]' => '/en_GB/stock?page=1',
        ];

        // submit the Form object
        $this->client->submit($form, $formFields);
        $this->assertResponseRedirects('/en_GB/stock?page=1', Response::HTTP_SEE_OTHER);
        $crawler = $this->client->followRedirect();
        $this->assertSelectorTextContains('.flash-success', self::$translator->trans('actionCompletedSuccessfully'));

        // submit when no referer
        $formFields[$formName . '[refererPage]'] = '';
        $this->client->submit($form, $formFields);
        $this->assertResponseRedirects('/en_GB/stock', Response::HTTP_SEE_OTHER);
        $crawler = $this->client->followRedirect();
        $this->assertSelectorTextContains('.flash-success', self::$translator->trans('actionCompletedSuccessfully'));

        // test domain exception
        $formFields[$formName . '[price]'] = '999999999';
        $this->client->submit($form, $formFields);
        $this->assertRouteSame('stock_update');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.flash-error', self::$translator->trans('enterNumberBetween', ['minimum' => StockPriceVO::VALUE_MIN, 'maximum' => StockPriceVO::VALUE_MAX], 'MiCarteraDomain'));
    }

    #[Depends('testUpdateStock')]
    public function testDelete(): void
    {
        $this->client->loginUser(self::getAuthUser());
        $crawler = $this->client->request('GET', '/en_GB/stock');

        // select the button
        $buttonCrawlerNode = $crawler->selectButton('cmdDelete_ABCD');

        // retrieve the Form object for the form belonging to this button
        $form = $buttonCrawlerNode->form();

        // submit the Form object
        $crawler = $this->client->submit($form);
        $this->assertResponseRedirects('/en_GB/stock', Response::HTTP_SEE_OTHER);
        $crawler = $this->client->followRedirect();
        $this->assertSelectorTextContains('.flash-success', self::$translator->trans('actionCompletedSuccessfully'));

        // test invalid token
        $values = $form->getValues();
        $values['_token'] = 'BADTOKEN';
        $form->setValues($values);
        $crawler = $this->client->submit($form);
        $this->assertResponseRedirects('http://localhost/en_GB/stock', Response::HTTP_SEE_OTHER);
        $crawler = $this->client->followRedirect();
        $this->assertSelectorTextContains('.flash-error', self::$translator->trans('invalidFormToken'));

        // test invalid token and no referer
        $values = $form->getValues();
        $values['_token'] = 'BADTOKEN';
        $form->setValues($values);
        $this->client->setServerParameter('HTTP_REFERER', '');
        $crawler = $this->client->submit($form);
        $this->assertResponseRedirects('/en_GB/stock', Response::HTTP_SEE_OTHER);
        $crawler = $this->client->followRedirect();
        $this->assertSelectorTextContains('.flash-error', self::$translator->trans('invalidFormToken'));

        // test domain exception
        $buttonCrawlerNode = $crawler->selectButton('cmdDelete_CABK');
        $form = $buttonCrawlerNode->form();
        $this->client->setServerParameter('HTTP_REFERER', '');
        $crawler = $this->client->submit($form);
        $this->assertResponseRedirects('/en_GB/stock', Response::HTTP_SEE_OTHER);
        $crawler = $this->client->followRedirect();
        $this->assertSelectorTextContains('.flash-error', self::$translator->trans('stockHasTransactions', [], 'MiCarteraDomain'));
    }
}
