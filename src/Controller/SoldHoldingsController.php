<?php

namespace App\Controller;

use App\Entity\Position;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SoldHoldingsController extends AbstractController
{
    #[Route('/sold-holdings', name: 'app_sold_holdings')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $positions = $entityManager->getRepository(Position::class)
            ->findSoldPositions();

        // Group sold positions by portfolio for easier display
        $soldData = [];
        $totals = [
            'count' => 0,
            'totalPL' => 0,
        ];

        foreach ($positions as $position) {
            $portfolio = $position->getPortfolio();
            if (!$portfolio) continue;

            $portfolioType = $portfolio->getAccountType();

            if (!isset($soldData[$portfolioType])) {
                $soldData[$portfolioType] = [
                    'positions' => [],
                    'count' => 0,
                ];
            }

            $soldData[$portfolioType]['positions'][] = $position;
            $soldData[$portfolioType]['count']++;
            $totals['count']++;

            // Sold positions keep their last known P/L value
            $totals['totalPL'] += $position->getProfitLoss();
        }

        return $this->render('sold_holdings/index.html.twig', [
            'soldData' => $soldData,
            'totals' => $totals,
        ]);
    }
}
