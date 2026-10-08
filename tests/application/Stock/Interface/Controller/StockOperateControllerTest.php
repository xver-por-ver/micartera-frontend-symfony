<?php

declare(strict_types=1);

namespace Tests\application\Stock\Interface\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Tests\application\ApplicationTestCase;
use Xver\MiCartera\Domain\Stock\Domain\Transaction\TransactionAmountVO;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Controller\StockAccountingController;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Controller\StockController;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Controller\StockOperateController;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Controller\StockPortfolioController;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Form\NumericStringData;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Form\StockOperateImportType;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Form\StockOperateType;

/**
 * @internal
 */
#[CoversClass(StockOperateController::class)]
#[CoversClass(StockOperateType::class)]
#[CoversClass(StockOperateImportType::class)]
#[UsesClass(NumericStringData::class)]
#[UsesClass(StockAccountingController::class)]
#[UsesClass(StockController::class)]
#[UsesClass(StockPortfolioController::class)]
class StockOperateControllerTest extends ApplicationTestCase
{
    public function testFromCsvWithWhiteLineDoesNotThrowException(): void
    {
        // Create temp file
        $filePath = '/tmp/micartera.csv';

        $this->client->loginUser(self::getAuthUser());
        $crawler = $this->client->request('GET', '/en_GB/stockoperate/import');

        // select the button
        $buttonCrawlerNode = $crawler->selectButton('stock_operate_import[upload]');

        // retrieve the Form object for the form belonging to this button
        $form = $buttonCrawlerNode->form();
        $formName = $form->getName();

        // Test add acquisition and liquidation
        $fp = fopen($filePath, 'w+');
        $fileContent = '' . PHP_EOL . '' . PHP_EOL;
        fputs($fp, $fileContent);
        $file = new UploadedFile($filePath, 'micartera.csv', null, \UPLOAD_ERR_PARTIAL, true);
        $formFields = [
            $formName . '[csv]' => $file,
        ];
        $this->client->submit($form, $formFields);
        $this->assertSelectorTextContains('.flash-error', self::$translator->trans('csvInvalidColumnCount', ['row' => 1, 'expected' => 6, 'got' => 1]));
    }

    public function testFromCsv(): void
    {
        // Create temp file
        $filePath = '/tmp/micartera.csv';

        $this->client->loginUser(self::getAuthUser());
        $crawler = $this->client->request('GET', '/en_GB/stockoperate/import');

        // select the button
        $buttonCrawlerNode = $crawler->selectButton('stock_operate_import[upload]');

        // retrieve the Form object for the form belonging to this button
        $form = $buttonCrawlerNode->form();
        $formName = $form->getName();

        // Test add acquisition and liquidation
        $fp = fopen($filePath, 'w+');
        $dateAcquisition = new \DateTime('30 mins ago', new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $dateLiquidation = new \DateTime('20 mins ago', new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $fileContent = $dateAcquisition . ',acquisition,SAN,1,2,3' . PHP_EOL;
        $fileContent .= $dateLiquidation . ',liquidation,SAN,1,2,3';
        fputs($fp, $fileContent);
        $file = new UploadedFile($filePath, 'micartera.csv', null, \UPLOAD_ERR_PARTIAL, true);
        $formFields = [
            $formName . '[csv]' => $file,
        ];
        fclose($fp);
        $this->client->submit($form, $formFields);
        $this->assertResponseRedirects('/en_GB/stock', Response::HTTP_SEE_OTHER);
        $crawler = $this->client->followRedirect();
        $this->assertSelectorTextContains('.flash-success', self::$translator->trans('actionCompletedSuccessfully'));

        // Test file upload error
        $file = new UploadedFile('/tmp/nonexistent.csv', 'micartera.csv', null, \UPLOAD_ERR_PARTIAL, true);
        $formFields = [
            $formName . '[csv]' => $file,
        ];
        $this->client->submit($form, $formFields);
        $this->assertSelectorTextContains('.flash-error', self::$translator->trans('invalidUploadedFile'));

        // Test invalid column count exception
        $fp = fopen($filePath, 'w+');
        $dateLiquidation = new \DateTime('20 mins ago', new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $fileContent = $dateLiquidation . ',liquidation,SAN,1,2';
        fputs($fp, $fileContent);
        $file = new UploadedFile($filePath, 'micartera.csv', null, \UPLOAD_ERR_PARTIAL, true);
        $formFields = [
            $formName . '[csv]' => $file,
        ];
        $this->client->submit($form, $formFields);
        $this->assertSelectorTextContains('.flash-error', self::$translator->trans('csvInvalidColumnCount', ['row' => 1, 'expected' => 6, 'got' => 5]));

        // Test domain exception
        $fp = fopen($filePath, 'w+');
        $dateLiquidation = new \DateTime('20 mins ago', new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $fileContent = $dateLiquidation . ',liquidation,NONEXISTENTSTOCK,1,2,3';
        fputs($fp, $fileContent);
        $file = new UploadedFile($filePath, 'micartera.csv', null, \UPLOAD_ERR_PARTIAL, true);
        $formFields = [
            $formName . '[csv]' => $file,
        ];
        $this->client->submit($form, $formFields);
        $errorMsg = self::$translator->trans('', ['row' => 1, 'field' => 'stock', 'error' => self::$translator->trans('entityNotFound', ['entity' => 'Stock', 'identifier' => '1'], 'PhpAppCore')], 'MiCarteraBackend');
        $this->assertSelectorTextContains('.flash-error', $errorMsg);
    }

    #[Depends('testFromCsv')]
    public function testAcquisition(): void
    {
        $this->client->loginUser(self::getAuthUser());
        $crawler = $this->client->request('GET', '/en_GB/stockoperate/purchase/SAN');

        // select the button
        $buttonCrawlerNode = $crawler->selectButton('stock_operate_cmdSubmit');

        // retrieve the Form object for the form belonging to this button
        $form = $buttonCrawlerNode->form();
        $formName = $form->getName();

        // set values on a form object
        $dateTime = new \DateTime('yesterday', new \DateTimeZone('UTC'));
        $formFields = [
            $formName . '[datetime]' => $dateTime->format('Y-m-d H:i:s'),
            $formName . '[amount]' => '100',
            $formName . '[price]' => '3.4566',
            $formName . '[expenses]' => '6.44',
        ];

        // test new
        $this->client->submit($form, $formFields);
        $this->assertResponseRedirects('/en_GB/stockportfolio', Response::HTTP_SEE_OTHER);
        $crawler = $this->client->followRedirect();
        $this->assertSelectorTextContains('.flash-success', self::$translator->trans('actionCompletedSuccessfully'));

        // test domain exception
        $formFields[$formName . '[amount]'] = 0;
        $this->client->submit($form, $formFields);
        $this->assertRouteSame('stockoperate_new');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains(
            '.flash-error',
            self::$translator->trans(
                'enterNumberBetween',
                ['minimum' => TransactionAmountVO::VALUE_MIN, 'maximum' => TransactionAmountVO::VALUE_MAX],
                'MiCarteraDomain'
            )
        );
    }

    #[Depends('testAcquisition')]
    public function testLiquidation(): void
    {
        $this->client->loginUser(self::getAuthUser());
        $crawler = $this->client->request('GET', '/en_GB/stockoperate/sell/SAN');

        // select the button
        $buttonCrawlerNode = $crawler->selectButton('stock_operate_cmdSubmit');

        // retrieve the Form object for the form belonging to this button
        $form = $buttonCrawlerNode->form();
        $formName = $form->getName();

        // set values on a form object
        $dateTime = new \DateTime('30 minutes ago', new \DateTimeZone('UTC'));
        $formFields = [
            $formName . '[datetime]' => $dateTime->format('Y-m-d H:i:s'),
            $formName . '[amount]' => '10',
            $formName . '[price]' => '3.4566',
            $formName . '[expenses]' => '6.44',
        ];

        // test new
        $this->client->submit($form, $formFields);
        $this->assertResponseRedirects('/en_GB/stockportfolio', Response::HTTP_SEE_OTHER);
        $crawler = $this->client->followRedirect();
        $this->assertSelectorTextContains('.flash-success', self::$translator->trans('actionCompletedSuccessfully'));

        // test new with referer
        $formFields[$formName . '[datetime]'] = new \DateTime('29 minutes ago', new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $referer = 'http://localhost/en_GB/stockportfolio?page=3';
        $formFields[$formName . '[refererPage]'] = $referer;
        $this->client->submit($form, $formFields);
        $this->assertResponseRedirects($referer, Response::HTTP_SEE_OTHER);
        $crawler = $this->client->followRedirect();
        $this->assertSelectorTextContains('.flash-success', self::$translator->trans('actionCompletedSuccessfully'));

        // test domain exception
        $formFields[$formName . '[amount]'] = 0;
        $this->client->submit($form, $formFields);
        $this->assertRouteSame('stockoperate_new');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains(
            '.flash-error',
            self::$translator->trans(
                'enterNumberBetween',
                ['minimum' => TransactionAmountVO::VALUE_MIN, 'maximum' => TransactionAmountVO::VALUE_MAX],
                'MiCarteraDomain'
            )
        );
    }

    #[Depends('testLiquidation')]
    public function testDeleteAcquisitionThrowsDomainError(): void
    {
        $this->client->loginUser(self::getAuthUser());
        $crawler = $this->client->request('GET', '/en_GB/stockportfolio');

        // select the button
        $buttonCrawlerNode = $crawler->selectButton('cmdDelete_1');

        // retrieve the Form object for the form belonging to this button
        $form = $buttonCrawlerNode->form();

        // submit the Form object
        $this->client->setServerParameter('HTTP_REFERER', '');
        $crawler = $this->client->submit($form);
        $this->assertResponseRedirects('/en_GB/stockportfolio', Response::HTTP_SEE_OTHER);
        $crawler = $this->client->followRedirect();
        $this->assertSelectorTextContains('.flash-error', self::$translator->trans('transBuyCannotBeRemovedWithoutFullAmountOutstanding', [], 'MiCarteraDomain'));
    }

    #[Depends('testDeleteAcquisitionThrowsDomainError')]
    public function testDeleteLiquidation(): void
    {
        $this->client->loginUser(self::getAuthUser());
        $crawler = $this->client->request('GET', '/en_GB/stockaccounting');

        // select the button
        $buttonCrawlerNode = $crawler->selectButton('cmdDelete_0');

        // retrieve the Form object for the form belonging to this button
        $form = $buttonCrawlerNode->form();

        // submit the Form object to delete liquidation
        $this->client->setServerParameter('HTTP_REFERER', '');
        $crawler = $this->client->submit($form);
        $this->assertResponseRedirects('/en_GB/stockaccounting', Response::HTTP_SEE_OTHER);
        $crawler = $this->client->followRedirect();
        $this->assertSelectorTextContains('.flash-success', self::$translator->trans('actionCompletedSuccessfully'));

        // submit the Form object to delete liquidation
        $buttonCrawlerNode = $crawler->selectButton('cmdDelete_0');
        $form = $buttonCrawlerNode->form();
        $crawler = $this->client->submit($form);
        $this->assertResponseRedirects('/en_GB/stockaccounting', Response::HTTP_SEE_OTHER);
        $crawler = $this->client->followRedirect();
        $this->assertSelectorTextContains('.flash-success', self::$translator->trans('actionCompletedSuccessfully'));

        // submit the Form object to delete last liquidation
        $buttonCrawlerNode = $crawler->selectButton('cmdDelete_0');
        $form = $buttonCrawlerNode->form();
        $crawler = $this->client->submit($form);
        $this->assertResponseRedirects('/en_GB/stockaccounting', Response::HTTP_SEE_OTHER);
        $crawler = $this->client->followRedirect();
        $this->assertSelectorTextContains('.flash-success', self::$translator->trans('actionCompletedSuccessfully'));

        // test invalid token
        $values = $form->getValues();
        $values['_token'] = 'BADTOKEN';
        $form->setValues($values);
        $referer = 'http://localhost/en_GB/stockaccounting?year=' . new \DateTime('now', new \DateTimeZone('UTC'))->format('Y');
        $this->client->setServerParameter('HTTP_REFERER', $referer);
        $crawler = $this->client->submit($form);
        $this->assertResponseRedirects($referer, Response::HTTP_SEE_OTHER);
        $crawler = $this->client->followRedirect();
        $this->assertSelectorTextContains('.flash-error', self::$translator->trans('invalidFormToken'));

        // test invalid token and no referer
        $values = $form->getValues();
        $values['_token'] = 'BADTOKEN';
        $form->setValues($values);
        $this->client->setServerParameter('HTTP_REFERER', '');
        $crawler = $this->client->submit($form);
        $this->assertResponseRedirects('/en_GB/stockaccounting', Response::HTTP_SEE_OTHER);
        $crawler = $this->client->followRedirect();
        $this->assertSelectorTextContains('.flash-error', self::$translator->trans('invalidFormToken'));
    }

    #[Depends('testDeleteLiquidation')]
    public function testDeleteAcquisition(): void
    {
        $this->client->loginUser(self::getAuthUser());
        $crawler = $this->client->request('GET', '/en_GB/stockportfolio');

        // select the button
        $buttonCrawlerNode = $crawler->selectButton('cmdDelete_1');

        // retrieve the Form object for the form belonging to this button
        $form = $buttonCrawlerNode->form();

        // submit the Form object
        $this->client->setServerParameter('HTTP_REFERER', '');
        $crawler = $this->client->submit($form);
        $this->assertResponseRedirects('/en_GB/stockportfolio', Response::HTTP_SEE_OTHER);
        $crawler = $this->client->followRedirect();
        $this->assertSelectorTextContains('.flash-success', self::$translator->trans('actionCompletedSuccessfully'));

        // test invalid token
        $values = $form->getValues();
        $values['_token'] = 'BADTOKEN';
        $form->setValues($values);
        $this->client->setServerParameter('HTTP_REFERER', '');
        $crawler = $this->client->submit($form);
        $this->assertResponseRedirects('/en_GB/stockportfolio', Response::HTTP_SEE_OTHER);
        $crawler = $this->client->followRedirect();
        $this->assertSelectorTextContains('.flash-error', self::$translator->trans('invalidFormToken'));
    }
}
