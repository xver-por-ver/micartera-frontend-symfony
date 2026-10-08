<?php

declare(strict_types=1);

namespace Tests\application\Account\Interface\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Component\HttpFoundation\Response;
use Tests\application\ApplicationTestCase;
use Xver\MiCartera\Frontend\Symfony\Account\Interface\Controller\AccountController;
use Xver\MiCartera\Frontend\Symfony\Account\Interface\Form\RegistrationFormType;
use Xver\MiCartera\Frontend\Symfony\Kernel;

/**
 * @internal
 */
#[CoversClass(AccountController::class)]
#[CoversClass(RegistrationFormType::class)]
#[UsesClass(Kernel::class)]
class AccountControllerTest extends ApplicationTestCase
{
    public function testRegister(): void
    {
        self::$loadFixtures = true;

        $crawler = $this->client->request('GET', '/en_GB/register');

        // select the button
        $buttonCrawlerNode = $crawler->selectButton('cmdSubmit');

        // retrieve the Form object for the form belonging to this button
        $form = $buttonCrawlerNode->form();
        $formName = $form->getName();

        // set values on a form object
        $formFields = [
            $formName . '[email]' => 'test2@example.com',
            $formName . '[plainPassword][first]' => 'password',
            $formName . '[plainPassword][second]' => 'password',
            $formName . '[currency]' => 'EUR',
            $formName . '[timezone]' => 'Europe/Madrid',
            $formName . '[agreeTerms]' => '1',
        ];

        // test new
        $this->client->submit($form, $formFields);
        $this->assertResponseRedirects('/en_GB/login', Response::HTTP_SEE_OTHER);
        $crawler = $this->client->followRedirect();
        $this->assertSelectorTextContains('.flash-success', self::$translator->trans('actionCompletedSuccessfully'));

        // test domain exception
        $formFields = [
            $formName . '[email]' => 'test@example.com',
            $formName . '[plainPassword][first]' => 'password',
            $formName . '[plainPassword][second]' => 'password',
            $formName . '[currency]' => 'EUR',
            $formName . '[timezone]' => 'Europe/Madrid',
            $formName . '[agreeTerms]' => '1',
        ];
        $crawler = $this->client->submit($form, $formFields);
        $this->assertRouteSame('app_register');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.flash-error', self::$translator->trans('accountEmailExists', [], 'SymfonyAuthBundle'));

        // symfony form validation
        $formFields = [
            $formName . '[email]' => 'test2@example.com',
            $formName . '[plainPassword][first]' => '123456',
            $formName . '[plainPassword][second]' => '123456',
            $formName . '[currency]' => 'EUR',
            $formName . '[timezone]' => 'Europe/Madrid',
            $formName . '[agreeTerms]' => '1',
        ];
        $this->client->submit($form, $formFields);
        $this->assertRouteSame('app_register');
        $this->assertResponseIsUnprocessable();
    }
}
