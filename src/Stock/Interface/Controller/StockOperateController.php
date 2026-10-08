<?php

declare(strict_types=1);

namespace Xver\MiCartera\Frontend\Symfony\Stock\Interface\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;
use Xver\PhpAppCoreBundle\Exception\Domain\DomainExceptionTranslator;
use Xver\PhpAppCoreBundle\Exception\Domain\DomainViolationException;
use Xver\MiCartera\Domain\Account\Domain\AccountPersistenceInterface;
use Xver\MiCartera\Domain\Stock\Application\Command\Transaction\AcquisitionCreateCommand;
use Xver\MiCartera\Domain\Stock\Application\Command\Transaction\AcquisitionDeleteCommand;
use Xver\MiCartera\Domain\Stock\Application\Command\Transaction\LiquidationCreateCommand;
use Xver\MiCartera\Domain\Stock\Application\Command\Transaction\LiquidationDeleteCommand;
use Xver\MiCartera\Domain\Stock\Application\Command\Transaction\StockOperationImportCommand;
use Xver\MiCartera\Domain\Stock\Domain\StockPersistenceInterface;
use Xver\MiCartera\Domain\Stock\Domain\Transaction\Accounting\MovementPersistenceInterface;
use Xver\MiCartera\Domain\Stock\Domain\Transaction\AcquisitionPersistenceInterface;
use Xver\MiCartera\Domain\Stock\Domain\Transaction\LiquidationPersistenceInterface;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Form\NumericStringData;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Form\StockOperateImportType;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Form\StockOperateType;

#[Route('/{_locale<%app.locales%>}/stockoperate', name: 'stockoperate_')]
final class StockOperateController extends AbstractController
{
    #[Route('/{type}/{stock}', name: 'new', methods: ['GET', 'POST'])]
    public function operate(
        Request $request,
        TranslatorInterface $translator,
        DomainExceptionTranslator $exceptionTranslator,
        AccountPersistenceInterface $accountPersistence,
        StockPersistenceInterface $stockPersistence,
        AcquisitionPersistenceInterface $acquisitionPersistence,
        LiquidationPersistenceInterface $liquidationPersistence,
        MovementPersistenceInterface $movementPersistence,
        NumericStringData $numericStringData
    ): Response {
        $formData = [
            'type' => match ((string) $request->attributes->get('type')) {
                'purchase' => 0,
                'sell' => 1
            },
            'stock' => $request->attributes->get('stock'),
        ];
        $request->isMethod('GET')
            ? $formData['refererPage'] = $request->headers->get('referer')
            : $formData['refererPage'] = (string) $request->request->all('stock_operate')['refererPage'];
        $form = $this->createForm(StockOperateType::class, $formData);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                /** @psalm-var \DateTime */
                $dateTime = $form->get('datetime')->getData();

                /** @psalm-suppress PossiblyNullReference */
                $userIdentifier = $this->getUser()->getUserIdentifier();

                $command
                    = (
                        0 === $formData['type']
                    ? new AcquisitionCreateCommand($acquisitionPersistence, $liquidationPersistence, $movementPersistence, $accountPersistence, $stockPersistence)
                    : new LiquidationCreateCommand($liquidationPersistence, $acquisitionPersistence, $movementPersistence, $accountPersistence, $stockPersistence)
                    )
                ;
                $command->invoke(
                    (string) $request->attributes->get('stock'),
                    $dateTime,
                    $numericStringData->from($form, 'amount'),
                    $numericStringData->from($form, 'price'),
                    $numericStringData->from($form, 'expenses'),
                    $userIdentifier
                );
                $this->addFlash('success', $translator->trans('actionCompletedSuccessfully'));

                return
                    $form->get('refererPage')->getData()
                    ? $this->redirect((string) $form->get('refererPage')->getData(), Response::HTTP_SEE_OTHER)
                    : $this->redirectToRoute('stockportfolio_index', [], Response::HTTP_SEE_OTHER);
            } catch (DomainViolationException $de) {
                $this->addFlash('error', $exceptionTranslator->getTranslatedException($de, $translator)->getMessage());
            }
        }

        return $this->render('stock/form.html.twig', [
            'form' => $form,
            'title' => $request->attributes->get('type'),
        ]);
    }

    #[Route('/{type}', name: 'delete', methods: ['DELETE'])]
    public function operationdelete(
        Request $request,
        TranslatorInterface $translator,
        DomainExceptionTranslator $exceptionTranslator,
        AcquisitionPersistenceInterface $acquisitionPersistence,
        LiquidationPersistenceInterface $liquidationPersistence,
        MovementPersistenceInterface $movementPersistence
    ): Response {
        $type = match ((string) $request->attributes->get('type')) {
            'purchase' => 0,
            'sell' => 1
        };

        /** @psalm-var string */
        $id = $request->request->get('id');
        $route = 0 === $type ? 'stockportfolio_index' : 'stockaccounting_index';
        if (false === $this->isCsrfTokenValid('delete' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', $translator->trans('invalidFormToken'));
        } else {
            try {
                $command = 0 === $type
                ? new AcquisitionDeleteCommand($acquisitionPersistence)
                : new LiquidationDeleteCommand($liquidationPersistence, $acquisitionPersistence, $movementPersistence);
                $command->invoke($id);
                $this->addFlash('success', $translator->trans('actionCompletedSuccessfully'));

                return $this->redirectToRoute($route, [], Response::HTTP_SEE_OTHER);
            } catch (DomainViolationException $de) {
                $this->addFlash('error', $exceptionTranslator->getTranslatedException($de, $translator)->getMessage());
            }
        }

        return
            '' != $request->headers->get('referer')
            ? $this->redirect((string) $request->headers->get('referer'), Response::HTTP_SEE_OTHER)
            : $this->redirectToRoute($route, [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/import', name: 'import', methods: ['GET', 'POST'])]
    public function fromcsv(
        Request $request,
        TranslatorInterface $translator,
        DomainExceptionTranslator $exceptionTranslator,
        AccountPersistenceInterface $accountPersistence,
        StockPersistenceInterface $stockPersistence,
        AcquisitionPersistenceInterface $acquisitionPersistence,
        LiquidationPersistenceInterface $liquidationPersistence,
        MovementPersistenceInterface $movementPersistence
    ): Response {
        $form = $this->createForm(StockOperateImportType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $file = $form->get('csv')->getData();

                if (
                    !$file instanceof UploadedFile
                    || UPLOAD_ERR_OK !== $file->getError()
                    || !$file->isValid()
                    || !$file->isFile()
                    || !in_array($file->getMimeType(), ['text/csv', 'text/plain', 'application/csv'])
                    || false === ($fp = fopen($file->getRealPath(), 'r'))
                ) {
                    throw new DomainViolationException(
                        new TranslatableMessage(
                            'invalidUploadedFile',
                            []
                        )
                    );
                }

                /** @psalm-suppress PossiblyNullReference */
                $userIdentifier = $this->getUser()->getUserIdentifier();
                $command = new StockOperationImportCommand($acquisitionPersistence, $liquidationPersistence, $movementPersistence, $accountPersistence, $stockPersistence);
                $lineNumber = 1;
                while (is_array($line = fgetcsv($fp, null, ',', '"', '\\'))) {
                    $numCols = count($line);
                    if (6 != $numCols) {
                        throw new DomainViolationException(
                            new TranslatableMessage(
                                'csvInvalidColumnCount',
                                [
                                    'row' => $lineNumber,
                                    'expected' => 6,
                                    'got' => $numCols,
                                ]
                            )
                        );
                    }

                    /**
                     * @psalm-var array{
                     *  0: string,1: string,2: string,3: numeric-string,4: numeric-string,5: numeric-string
                     * } $line
                     */
                    $command->invoke(
                        $lineNumber,
                        $line,
                        $userIdentifier
                    );
                    ++$lineNumber;
                }
                fclose($fp);
                $this->addFlash('success', $translator->trans('actionCompletedSuccessfully'));

                return $this->redirectToRoute('stock_list', [], Response::HTTP_SEE_OTHER);
            } catch (DomainViolationException $de) {
                $this->addFlash('error', $exceptionTranslator->getTranslatedException($de, $translator)->getMessage());
            }
        }

        return $this->render('stock/form.html.twig', [
            'form' => $form,
            'title' => $translator->trans('operationsbatchimport'),
        ]);
    }
}
