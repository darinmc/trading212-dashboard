<?php

namespace App\Controller;

use App\Entity\Portfolio;
use App\Entity\Position;
use App\Entity\Transaction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SippTradeController extends AbstractController
{
    #[Route('/sipp-trade', name: 'app_sipp_trade', methods: ['POST'])]
    public function recordTrade(Request $request, EntityManagerInterface $entityManager): Response
    {
        $ticker = strtoupper(trim($request->request->get('ticker')));
        $type = $request->request->get('type'); // BUY or SELL
        $shares = (float) $request->request->get('shares');
        $price = (float) $request->request->get('price');
        $currency = $request->request->get('currency', 'GBP');

        // Convert USD to GBP using the current approximate rate
        if ($currency === 'USD') {
            $exchangeRate = 1.32; // You can update this rate as needed
            $price = $price / $exchangeRate;
        }
        $fees = (float) $request->request->get('fees', 0);
        $date = $request->request->get('date') ? new \DateTime($request->request->get('date')) : new \DateTime();

        if (!$ticker || $shares <= 0 || $price <= 0) {
            $this->addFlash('error', 'Invalid ticker, shares, or price.');
            return $this->redirectToRoute('app_master_dashboard');
        }

        // 1. Find the SIPP Portfolio
        $portfolio = $entityManager->getRepository(Portfolio::class)
            ->findOneBy(['accountType' => 'SIPP']);

        if (!$portfolio) {
            $this->addFlash('error', 'SIPP portfolio not found.');
            return $this->redirectToRoute('app_master_dashboard');
        }

        // 2. Find or Create the Position
        $position = $entityManager->getRepository(Position::class)
            ->findOneBy(['ticker' => $ticker, 'portfolio' => $portfolio]);

        if (!$position) {
            if ($type === 'SELL') {
                $this->addFlash('error', "You don't hold any $ticker in your SIPP to sell.");
                return $this->redirectToRoute('app_master_dashboard');
            }
            
            // Create new position if buying a new stock
            $position = new Position();
            $position->setTicker($ticker);
            $position->setName($ticker);
            $position->setPortfolio($portfolio);
            $position->setShares(0);
            $position->setAvgPrice(0);
            $position->setTotalSpent(0);
            $position->setCurrentPrice($price);
            $position->setIsActive(true);
            $entityManager->persist($position);
        }

        $totalValue = ($shares * $price) + $fees;

        // 3. Create the Transaction History Record
        $tx = new Transaction();
        $tx->setPosition($position);
        $tx->setTicker($ticker);
        $tx->setAccountType('SIPP');
        $tx->setType($type);
        $tx->setShares($shares);
        $tx->setPricePerShare($price);
        $tx->setTotalValue($totalValue);
        $tx->setAvgPriceAtTime($position->getAvgPrice());
        $tx->setTradeDate($date);
        $tx->setSource(Transaction::SOURCE_CRUD); // Marks it as manual
        $tx->setNetValueGbp($totalValue);

        if ($type === 'SELL') {
            $costBasis = $shares * $position->getAvgPrice();
            $realisedPl = $totalValue - $costBasis;
            $tx->setRealisedPl($realisedPl);
            $tx->setRealisedPlPercent($costBasis > 0 ? ($realisedPl / $costBasis) * 100 : 0);
        }

        $entityManager->persist($tx);

        // 4. Update the Position Math
        if ($type === 'BUY') {
            $newTotalShares = $position->getShares() + $shares;
            $newTotalSpent = $position->getTotalSpent() + $totalValue;
            $newAvgPrice = $newTotalShares > 0 ? ($newTotalSpent / $newTotalShares) : 0;
            
            $position->setShares($newTotalShares);
            $position->setTotalSpent($newTotalSpent);
            $position->setAvgPrice($newAvgPrice);
            $position->setCurrentPrice($price); // Assume latest price is the trade price
        } else {
            $newShares = $position->getShares() - $shares;
            if ($newShares <= 0) {
                $newShares = 0;
                $position->setIsActive(false);
                $position->setSoldDate($date);
            }
            $position->setShares($newShares);
        }

        // Recalculate Current Value & P/L
        $currentValue = $position->getShares() * $position->getCurrentPrice();
        $position->setCurrentValue($currentValue);
        $position->setProfitLoss($currentValue - $position->getTotalSpent());
        $position->setProfitLossPercent($position->getTotalSpent() > 0 ? (($currentValue - $position->getTotalSpent()) / $position->getTotalSpent()) * 100 : 0);
        $position->setLastUpdated(new \DateTime());

        $entityManager->flush();

        $this->addFlash('success', "✅ Successfully recorded SIPP $type of $shares $ticker shares.");
        
        return $this->redirectToRoute('app_master_dashboard');
    }
}
