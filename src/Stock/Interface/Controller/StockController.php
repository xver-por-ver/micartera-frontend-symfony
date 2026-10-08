<?php

declare(strict_types=1);

namespace Xver\MiCartera\Frontend\Symfony\Stock\Interface\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Xver\PhpAppCoreBundle\Exception\Domain\DomainExceptionTranslator;
use Xver\PhpAppCoreBundle\Exception\Domain\DomainViolationException;
use Xver\MiCartera\Domain\Account\Domain\AccountPersistenceInterface;
use Xver\MiCartera\Domain\Exchange\Domain\Exchange;
use Xver\MiCartera\Domain\Exchange\Domain\ExchangePersistenceInterface;
use Xver\MiCartera\Domain\Stock\Application\Command\StockCreateCommand;
use Xver\MiCartera\Domain\Stock\Application\Command\StockDeleteCommand;
use Xver\MiCartera\Domain\Stock\Application\Command\StockUpdateCommand;
use Xver\MiCartera\Domain\Stock\Application\Query\Portfolio\PortfolioQuery;
use Xver\MiCartera\Domain\Stock\Application\Query\StockQuery;
use Xver\MiCartera\Domain\Stock\Domain\StockPersistenceInterface;
use Xver\MiCartera\Domain\Stock\Domain\Transaction\AcquisitionPersistenceInterface;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Form\NumericStringData;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Form\NumericStringFormData;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Form\StockType;

#[Route('/{_locale<%app.locales%>}/stock', name: 'stock_')]
final class StockController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function index(
        Request $request,
        AccountPersistenceInterface $accountPersistence,
        StockPersistenceInterface $stockPersistence
    ): Response {
        /** @psalm-suppress PossiblyNullReference */
        $userIdentifier = $this->getUser()->getUserIdentifier();
        $query = new StockQuery($stockPersistence, $accountPersistence);
        $queryResponse = $query->byAccountsCurrency(
            $userIdentifier,
            10,
            (int) $request->query->get('page', 0)
        );

        return $this->render(
            'stock/index.html.twig',
            [
                'stocks' => $queryResponse,
                'currencySymbol' => $query->currencySymbol,
            ]
        );
    }

    #[Route('/form-new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        TranslatorInterface $translator,
        DomainExceptionTranslator $exceptionTranslator,
        AccountPersistenceInterface $accountPersistence,
        StockPersistenceInterface $stockPersistence,
        ExchangePersistenceInterface $exchangePersistence,
        NumericStringData $numericStringData
    ): RedirectResponse|Response {
        $form = $this->createForm(StockType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $command = new StockCreateCommand($stockPersistence, $accountPersistence, $exchangePersistence);

                /** @psalm-suppress PossiblyNullReference */
                $userIdentifier = $this->getUser()->getUserIdentifier();

                /** @psalm-var Exchange */
                $exchange = $form->get('exchange')->getData();
                $command->invoke(
                    (string) $form->get('code')->getData(),
                    (string) $form->get('name')->getData(),
                    $numericStringData->from($form, 'price'),
                    $userIdentifier,
                    $exchange->getCode()
                );
                $this->addFlash('success', $translator->trans('actionCompletedSuccessfully'));

                return $this->redirectToRoute('stock_list', [], Response::HTTP_SEE_OTHER);
            } catch (DomainViolationException $th) {
                $this->addFlash('error', $exceptionTranslator->getTranslatedException($th, $translator)->getMessage());
            }
        }

        return $this->render('stock/form.html.twig', [
            'form' => $form,
            'title' => 'newStock',
        ]);
    }

    #[Route('/{id}', name: 'update', methods: ['GET', 'POST'])]
    public function update(
        Request $request,
        TranslatorInterface $translator,
        DomainExceptionTranslator $exceptionTranslator,
        AccountPersistenceInterface $accountPersistence,
        StockPersistenceInterface $stockPersistence,
        AcquisitionPersistenceInterface $acquisitionPersistence,
        NumericStringData $numericStringData
    ): RedirectResponse|Response {
        $request->isMethod('GET')
            ? $formData = [
                'code' => $request->attributes->get('id'),
                'refererPage' => $request->headers->get('referer'),
            ]
            : $formData = [
                'code' => $request->attributes->get('id'),
                'refererPage' => $request->request->all('stock')['refererPage'],
            ];
        $form = $this->createForm(StockType::class, $formData);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $command = new StockUpdateCommand($stockPersistence);
                $command->invoke((string) $formData['code'], (string) $form->get('name')->getData(), $numericStringData->from($form, 'price'));
                $this->addFlash('success', $translator->trans('actionCompletedSuccessfully'));

                return
                    $form->get('refererPage')->getData()
                    ? $this->redirect((string) $form->get('refererPage')->getData(), Response::HTTP_SEE_OTHER)
                    : $this->redirectToRoute('stock_list', [], Response::HTTP_SEE_OTHER);
            } catch (DomainViolationException $de) {
                $this->addFlash('error', $exceptionTranslator->getTranslatedException($de, $translator)->getMessage());
            }
        }

        /** @psalm-suppress PossiblyNullReference */
        $userIdentifier = $this->getUser()->getUserIdentifier();
        $query = new PortfolioQuery($stockPersistence, $accountPersistence, $acquisitionPersistence);
        $summaryVO = $query->getStockPortfolioSummary(
            $userIdentifier,
            (string) $formData['code']
        );

        return $this->render('stock/form.html.twig', [
            'form' => $form,
            'title' => $translator->trans('editStock'),
            'summary' => $summaryVO,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(
        Request $request,
        TranslatorInterface $translator,
        DomainExceptionTranslator $exceptionTranslator,
        StockPersistenceInterface $stockPersistence
    ): RedirectResponse|Response {
        /** @psalm-var string */
        $id = $request->attributes->get('id');
        if (false === $this->isCsrfTokenValid('delete' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', $translator->trans('invalidFormToken'));
        } else {
            try {
                $command = new StockDeleteCommand($stockPersistence);
                $command->invoke($id);
                $this->addFlash('success', $translator->trans('actionCompletedSuccessfully'));

                return $this->redirectToRoute('stock_list', [], Response::HTTP_SEE_OTHER);
            } catch (DomainViolationException $de) {
                $this->addFlash('error', $exceptionTranslator->getTranslatedException($de, $translator)->getMessage());
            }
        }

        return
            '' != $request->headers->get('referer')
            ? $this->redirect((string) $request->headers->get('referer'), Response::HTTP_SEE_OTHER)
            : $this->redirectToRoute('stock_list', [], Response::HTTP_SEE_OTHER);
    }
}
