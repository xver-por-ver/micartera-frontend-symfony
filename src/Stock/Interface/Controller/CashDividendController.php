<?php

declare(strict_types=1);

namespace Xver\MiCartera\Frontend\Symfony\Stock\Interface\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;
use Xver\MiCartera\Domain\Stock\Application\Command\Dividend\CashDividendCreateCommand;
use Xver\MiCartera\Domain\Stock\Application\Command\Dividend\CashDividendDeleteCommand;
use Xver\MiCartera\Domain\Stock\Application\Command\Dividend\CashDividendUpdateCommand;
use Xver\MiCartera\Domain\Stock\Domain\Dividend\CashDividendAmountException;
use Xver\MiCartera\Domain\Stock\Domain\Dividend\CashDividendPersistenceInterface;
use Xver\MiCartera\Domain\Account\Domain\AccountPersistenceInterface;
use Xver\MiCartera\Domain\Stock\Domain\StockPersistenceInterface;
use Xver\MiCartera\Domain\Stock\Domain\Transaction\AcquisitionPersistenceInterface;
use Xver\MiCartera\Domain\Stock\Domain\Transaction\LiquidationPersistenceInterface;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Form\CashDividendType;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Form\NumericStringData;
use Xver\PhpAppCoreBundle\Exception\Domain\DomainExceptionTranslator;
use Xver\PhpAppCoreBundle\Exception\Domain\DomainViolationException;

#[Route('/{_locale<%app.locales%>}/cashdividend', name: 'cashdividend_')]
final class CashDividendController extends AbstractController
{
    #[Route('/new/{stock}', name: 'new', methods: ['GET', 'POST'])]
    public function create(
        string $stock,
        Request $request,
        TranslatorInterface $translator,
        DomainExceptionTranslator $exceptionTranslator,
        CashDividendPersistenceInterface $cashDividendPersistence,
        AcquisitionPersistenceInterface $acquisitionPersistence,
        LiquidationPersistenceInterface $liquidationPersistence,
        AccountPersistenceInterface $accountPersistence,
        StockPersistenceInterface $stockPersistence,
        NumericStringData $numericStringData
    ): Response {
        $form = $this->createForm(CashDividendType::class, [
            'refererPage' => $request->headers->get('referer', ''),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                /** @psalm-suppress PossiblyNullReference */
                $identifier = $this->getUser()->getUserIdentifier();
                /** @var \DateTime $dateTime */
                $dateTime = $form->get('datetime')->getData();
                new CashDividendCreateCommand(
                    $cashDividendPersistence,
                    $acquisitionPersistence,
                    $liquidationPersistence,
                    $accountPersistence,
                    $stockPersistence
                )->invoke(
                    $stock,
                    $dateTime,
                    $numericStringData->from($form, 'dividendPerShare'),
                    $identifier,
                    $numericStringData->from($form, 'expenses')
                );
                $this->addFlash('success', $translator->trans('actionCompletedSuccessfully'));

                return $this->redirectToPreviousPage($request, (string) $form->get('refererPage')->getData());
            } catch (CashDividendAmountException $exception) {
                $this->addFlash('error', $exception->getTranslatableMessage()->trans($translator));
            } catch (DomainViolationException $exception) {
                $this->addFlash('error', $exceptionTranslator->getTranslatedException($exception, $translator)->getMessage());
            }
        }

        return $this->render('stock/dividend/form.html.twig', ['form' => $form, 'stockCode' => $stock]);
    }

    private function redirectToPreviousPage(Request $request, string $referer): Response
    {
        $url = parse_url($referer);
        if (false === $url) {
            return $this->redirectToRoute('stockportfolio_index', [], Response::HTTP_SEE_OTHER);
        }

        $locale = (string) $request->attributes->get('_locale');
        $expectedOrigin = parse_url($request->getSchemeAndHttpHost());
        $sameOrigin = !isset($url['host']) || (
            ($url['scheme'] ?? null) === ($expectedOrigin['scheme'] ?? null)
            && $url['host'] === ($expectedOrigin['host'] ?? null)
            && ($url['port'] ?? null) === ($expectedOrigin['port'] ?? null)
        );
        $path = $url['path'] ?? '';

        if (
            $sameOrigin
            && str_starts_with($path, '/' . $locale . '/')
            && !str_starts_with($path, '//')
        ) {
            return $this->redirect($referer, Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('stockportfolio_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(
        string $id,
        Request $request,
        TranslatorInterface $translator,
        DomainExceptionTranslator $exceptionTranslator,
        CashDividendPersistenceInterface $cashDividendPersistence
    ): Response {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }
        $cashDividend = $cashDividendPersistence->getRepository()->findByIdOrThrowException(new Uuid($id));
        /** @psalm-suppress PossiblyNullReference */
        if ($cashDividend->getAccount()->getIdentifier() !== $this->getUser()->getUserIdentifier()) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('delete' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', $translator->trans('invalidFormToken'));
        } else {
            try {
                (new CashDividendDeleteCommand($cashDividendPersistence))->invoke($id);
                $this->addFlash('success', $translator->trans('actionCompletedSuccessfully'));

                return $this->redirectToRoute('stock_update', ['id' => $cashDividend->getStock()->getId()], Response::HTTP_SEE_OTHER);
            } catch (DomainViolationException $exception) {
                $this->addFlash('error', $exceptionTranslator->getTranslatedException($exception, $translator)->getMessage());
            }
        }

        return $this->redirectToRoute('stock_update', ['id' => $cashDividend->getStock()->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        string $id,
        Request $request,
        TranslatorInterface $translator,
        DomainExceptionTranslator $exceptionTranslator,
        CashDividendPersistenceInterface $cashDividendPersistence,
        NumericStringData $numericStringData
    ): Response {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }
        $cashDividend = $cashDividendPersistence->getRepository()->findByIdOrThrowException(new Uuid($id));
        /** @psalm-suppress PossiblyNullReference */
        if ($cashDividend->getAccount()->getIdentifier() !== $this->getUser()->getUserIdentifier()) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(CashDividendType::class, [
            'dividendPerShare' => $cashDividend->getDividendPerShare()->getValue(),
            'expenses' => $cashDividend->getExpenses()->getValue(),
        ], [
            'include_datetime' => false,
            'submit_label' => 'updateCashDividend',
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                (new CashDividendUpdateCommand($cashDividendPersistence))->invoke(
                    $id,
                    $numericStringData->from($form, 'dividendPerShare'),
                    $numericStringData->from($form, 'expenses')
                );
                $this->addFlash('success', $translator->trans('actionCompletedSuccessfully'));

                return $this->redirectToRoute('stock_update', ['id' => $cashDividend->getStock()->getId()], Response::HTTP_SEE_OTHER);
            } catch (DomainViolationException $exception) {
                $this->addFlash('error', $exceptionTranslator->getTranslatedException($exception, $translator)->getMessage());
            }
        }

        return $this->render('stock/dividend/form.html.twig', [
            'form' => $form,
            'stockCode' => $cashDividend->getStock()->getId(),
            'edit' => true,
            'recordedDate' => $cashDividend->getDateTimeUtc(),
        ]);
    }
}
