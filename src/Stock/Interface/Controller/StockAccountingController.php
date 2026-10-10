<?php

declare(strict_types=1);

namespace Xver\MiCartera\Frontend\Symfony\Stock\Interface\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Xver\MiCartera\Domain\Account\Domain\AccountPersistenceInterface;
use Xver\MiCartera\Domain\Stock\Application\Query\Transaction\Accounting\AccountingQuery;
use Xver\MiCartera\Domain\Stock\Domain\Dividend\CashDividendPersistenceInterface;
use Xver\MiCartera\Domain\Stock\Domain\Transaction\Accounting\MovementPersistenceInterface;

#[Route('/{_locale<%app.locales%>}/stockaccounting')]
final class StockAccountingController extends AbstractController
{
    #[Route('', name: 'stockaccounting_index', methods: ['GET'])]
    public function index(
        Request $request,
        AccountPersistenceInterface $accountPersistence,
        MovementPersistenceInterface $movementPersistence,
        CashDividendPersistenceInterface $cashDividendPersistence
    ): Response {
        /** @psalm-suppress PossiblyNullReference */
        $userIdentifier = $this->getUser()->getUserIdentifier();
        $query = new AccountingQuery($accountPersistence, $movementPersistence, $cashDividendPersistence);
        $accountingDTO = $query->byAccountYear(
            $userIdentifier,
            false === is_null($request->query->get('year')) ? (int) $request->query->get('year') : null,
            20,
            (int) $request->query->get('page', 0)
        );

        return $this->render('stock/accounting/index.html.twig', ['accounting' => $accountingDTO]);
    }
}
