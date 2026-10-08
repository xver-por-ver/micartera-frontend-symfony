<?php

declare(strict_types=1);

namespace Xver\MiCartera\Frontend\Symfony\Account\Interface\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\Translation\TranslatorInterface;
use Xver\PhpAppCoreBundle\Exception\Domain\DomainExceptionTranslator;
use Xver\PhpAuthCoreBundle\Auth\Domain\AuthUser;
use Xver\MiCartera\Domain\Account\Application\Command\AccountCreateCommand;
use Xver\MiCartera\Domain\Account\Domain\AccountPersistenceInterface;
use Xver\MiCartera\Frontend\Symfony\Account\Interface\Form\RegistrationFormType;

final class AccountController extends AbstractController
{
    #[Route('/', name: 'main_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('stockportfolio_index');
    }

    #[Route('/{_locale<%app.locales%>}/login', name: 'app_login')]
    public function login(
        AuthenticationUtils $authenticationUtils,
        TranslatorInterface $translator
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('stockportfolio_index');
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render(
            'security/login.html.twig',
            [
                'last_username' => $lastUsername,
                'error' => $error,
                'formFooterLinks' => [
                    ['href' => $this->generateUrl('app_register'), 'text' => $translator->trans('signUp', [], 'PhpAuthCoreBundle')],
                ],
            ],
            is_null($error) ? null : new Response('', 401)
        );
    }

    /**
     * This method can be blank - it will be intercepted by the logout key on your firewall.
     *
     * @codeCoverageIgnore
     */
    #[Route('/{_locale<%app.locales%>}/logout', name: 'app_logout')]
    public function logout(): void {}

    #[Route('/{_locale<%app.locales%>}/register', name: 'app_register')]
    public function register(
        Request $request,
        TranslatorInterface $translator,
        UserPasswordHasherInterface $passwordHasher,
        DomainExceptionTranslator $exceptionTranslator,
        AccountPersistenceInterface $accountPersistence
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('stockportfolio_index');
        }

        $options = [
            'agreeTerms_label' => $translator->trans(
                'linkTerms',
                [
                    '{link_open}' => sprintf(
                        '<a href="%s" target="_blank" class="termsLink">',
                        $this->generateUrl('terms_conditions')
                    ),
                    '{link_close}' => '</a>',
                ]
            ),
        ];
        $form = $this->createForm(RegistrationFormType::class, [], $options);
        $form->handleRequest($request);
        // @var non-empty-string
        $identifier = (string) $form->get('email')->getData();
        if ($form->isSubmitted() && $form->isValid() && !empty($identifier)) {
            try {
                $command = new AccountCreateCommand($accountPersistence);
                $roles = ['ROLE_USER'];
                $hashedPassword = $passwordHasher->hashPassword(
                    new AuthUser(
                        $identifier,
                        $roles,
                        'empty'
                    ),
                    (string) $form->get('plainPassword')->getData()
                );

                /**
                 * @psalm-var non-empty-string
                 */
                $email = (string) $form->get('email')->getData();

                /**
                 * @psalm-suppress MixedAssignment
                 * @psalm-suppress MixedMethodCall
                 *
                 * @psalm-var non-empty-string
                 */
                $currencyIso3 = (string) $form->get('currency')->getData()->getIso3();

                /**
                 * @psalm-var \DateTimeZone
                 */
                $timezone = $form->get('timezone')->getData();
                $command->invoke(
                    $email,
                    $hashedPassword,
                    $currencyIso3,
                    $timezone,
                    $roles,
                    (bool) $form->get('agreeTerms')->getData()
                );
                $this->addFlash('success', $translator->trans('actionCompletedSuccessfully'));

                return $this->redirectToRoute('app_login', [], Response::HTTP_SEE_OTHER);
            } catch (\Throwable $de) {
                $this->addFlash('error', $exceptionTranslator->getTranslatedException($de, $translator)->getMessage());
            }
        }

        return $this->render(
            'form/reusable_form.html.twig',
            [
                'form' => $form,
                'formTitle' => $translator->trans('signUp', [], 'PhpAuthCoreBundle'),
                'formSubmit' => $translator->trans('signUp', [], 'PhpAuthCoreBundle'),
                'formFooterLinks' => [
                    ['href' => $this->generateUrl('app_login'), 'text' => $translator->trans('signIn', [], 'PhpAuthCoreBundle')],
                ],
            ]
        );
    }

    #[Route('/{_locale<%app.locales%>}/terms-conditions', name: 'terms_conditions', methods: ['GET'])]
    public function termsConditions(): Response
    {
        return $this->render('security/terms.html.twig');
    }
}
