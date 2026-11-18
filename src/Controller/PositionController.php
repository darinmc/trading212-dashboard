<?php

namespace App\Controller;

use App\Entity\Position;
use App\Entity\Portfolio;
use App\Entity\Transaction;
use App\Form\PositionType;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/position')]
class PositionController extends AbstractController
{
    #[Route('/', name: 'app_position_index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $search = $request->query->get('search', '');
        $accountFilter = $request->query->get('account', '');
        $sort = $request->query->get('sort', 'ticker');
        $direction = $request->query->get('direction', 'ASC');

        $positionRepository = $entityManager->getRepository(Position::class);

        $qb = $positionRepository->createQueryBuilder('p')
            ->leftJoin('p.portfolio', 'port')
            ->addSelect('port');

        if ($search) {
            $qb->andWhere('p.ticker LIKE :search OR p.name LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($accountFilter) {
            $qb->andWhere('port.accountType = :account')
               ->setParameter('account', $accountFilter);
        }

        $allowedSorts = ['ticker', 'name', 'shares', 'avgPrice', 'currentPrice', 'totalSpent', 'currentValue', 'profitLoss', 'profitLossPercent'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'ticker';
        }

        if (!in_array($direction, ['ASC', 'DESC'])) {
            $direction = 'ASC';
        }

        $qb->orderBy('p.' . $sort, $direction);

        $positions = $qb->getQuery()->getResult();

        $totalCurrentValue = 0;
        $totalPL = 0;
        foreach ($positions as $position) {
            $totalCurrentValue += $position->getCurrentValue() ?? 0;
            $totalPL += $position->getProfitLoss() ?? 0;
        }

        return $this->render('position/index.html.twig', [
            'positions' => $positions,
            'search' => $search,
            'accountFilter' => $accountFilter,
            'sort' => $sort,
            'direction' => $direction,
            'totalCurrentValue' => $totalCurrentValue,
            'totalPL' => $totalPL,
        ]);
    }

    #[Route('/new', name: 'app_position_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $position = new Position();
        $form = $this->createForm(PositionType::class, $position);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $shares = $position->getShares();
            $avgPrice = $position->getAvgPrice();
            $currentPrice = $position->getCurrentPrice();

            $totalSpent = $shares * $avgPrice;
            $currentValue = $shares * $currentPrice;
            $profitLoss = $currentValue - $totalSpent;
            $profitLossPercent = ($totalSpent > 0) ? ($profitLoss / $totalSpent) * 100 : 0;

            $position->setTotalSpent($totalSpent);
            $position->setCurrentValue($currentValue);
            $position->setProfitLoss($profitLoss);
            $position->setProfitLossPercent($profitLossPercent);
            $position->setLastUpdated(new \DateTime());

            $entityManager->persist($position);
            $entityManager->flush();

            return $this->redirectToRoute('app_position_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('position/new.html.twig', [
            'position' => $position,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_position_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Position $position, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PositionType::class, $position);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $shares = $position->getShares();
            $avgPrice = $position->getAvgPrice();
            $currentPrice = $position->getCurrentPrice();

            $totalSpent = $shares * $avgPrice;
            $currentValue = $shares * $currentPrice;
            $profitLoss = $currentValue - $totalSpent;
            $profitLossPercent = ($totalSpent > 0) ? ($profitLoss / $totalSpent) * 100 : 0;

            $position->setTotalSpent($totalSpent);
            $position->setCurrentValue($currentValue);
            $position->setProfitLoss($profitLoss);
            $position->setProfitLossPercent($profitLossPercent);
            $position->setLastUpdated(new \DateTime());

            $entityManager->flush();

            $params = [];
            if ($request->query->get('search')) $params['search'] = $request->query->get('search');
            if ($request->query->get('account')) $params['account'] = $request->query->get('account');
            
            return $this->redirectToRoute('app_position_index', $params, Response::HTTP_SEE_OTHER);
        }

            return $this->renderForm('position/edit.html.twig', [
            'position' => $position,
            'form' => $form,
            'search' => $request->query->get('search', ''),
            'accountFilter' => $request->query->get('account', ''),
        ]);
    }

    #[Route('/{id}/manual-trade', name: 'app_position_manual_trade', methods: ['POST'])]
    public function manualTrade(Request $request, Position $position, EntityManagerInterface $entityManager): Response
    {
        $type = $request->request->get('type'); // BUY or SELL
        $shares = (float) $request->request->get('shares');
        $price = (float) $request->request->get('price');
        $currency = $request->request->get('currency', 'GBP');
        $fees = (float) $request->request->get('fees', 0);

        // Convert USD to GBP using the current approximate rate
        if ($currency === 'USD') {
            $price = $price / 1.32;
        }
        $date = $request->request->get('date') ? new \DateTime($request->request->get('date')) : new \DateTime();
        
        if ($shares <= 0 || $price < 0) {
            $this->addFlash('error', 'Invalid shares or price.');
            return $this->redirectToRoute('app_position_index');
        }

        $totalValue = ($shares * $price) + $fees;
        
        // 1. Create the transaction
        $tx = new Transaction();
        $tx->setPosition($position);
        $tx->setTicker($position->getTicker());
        $tx->setAccountType($position->getPortfolio()->getAccountType());
        $tx->setType($type);
        $tx->setShares($shares);
        $tx->setPricePerShare($price);
        $tx->setTotalValue($totalValue);
        $tx->setAvgPriceAtTime($position->getAvgPrice());
        $tx->setTradeDate($date);
        $tx->setSource(Transaction::SOURCE_CRUD);
        $tx->setNetValueGbp($totalValue);

        if ($type === Transaction::TYPE_SELL) {
            $costBasis = $shares * $position->getAvgPrice();
            $realisedPl = $totalValue - $costBasis;
            $tx->setRealisedPl($realisedPl);
            $tx->setRealisedPlPercent($costBasis > 0 ? ($realisedPl / $costBasis) * 100 : 0);
        }

        $entityManager->persist($tx);

        // 2. Update the position shares
        if ($type === Transaction::TYPE_BUY) {
            $newTotalShares = $position->getShares() + $shares;
            $newTotalSpent = $position->getTotalSpent() + $totalValue;
            $newAvgPrice = $newTotalShares > 0 ? ($newTotalSpent / $newTotalShares) : 0;
            
            $position->setShares($newTotalShares);
            $position->setTotalSpent($newTotalSpent);
            $position->setAvgPrice($newAvgPrice);
            
            // Update current value/P&L based on current price
            $currentValue = $newTotalShares * $position->getCurrentPrice();
            $position->setCurrentValue($currentValue);
            $position->setProfitLoss($currentValue - $newTotalSpent);
            
        } else {
            $newShares = $position->getShares() - $shares;
            if ($newShares <= 0) {
                $newShares = 0;
                $position->setIsActive(false);
                $position->setSoldDate($date);
            }
            $position->setShares($newShares);
            
            // Update current value/P&L
            $currentValue = $newShares * $position->getCurrentPrice();
            $position->setCurrentValue($currentValue);
            $position->setProfitLoss($currentValue - ($newShares * $position->getAvgPrice()));
        }

        $entityManager->flush();

        $this->addFlash('success', "Successfully recorded $type of $shares shares.");
        
        $params = [];
        if ($request->query->get('search')) $params['search'] = $request->query->get('search');
        if ($request->query->get('account')) $params['account'] = $request->query->get('account');
        
        return $this->redirectToRoute('app_position_index', $params);
    }

    #[Route('/{id}', name: 'app_position_delete', methods: ['POST'])]
    public function delete(Request $request, Position $position, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$position->getId(), $request->request->get('_token'))) {
            $entityManager->remove($position);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_position_index', [], Response::HTTP_SEE_OTHER);
    }
}
