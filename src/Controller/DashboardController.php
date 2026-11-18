<?php


namespace App\Controller;


use App\Entity\Position;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\Routing\Annotation\Route;


class DashboardController extends AbstractController

{

        #[Route('/old-dashboard', name: 'app_dashboard')]

    public function index(EntityManagerInterface $entityManager): Response

    {

        // Get all active (not sold) positions grouped by portfolio

        $positions = $entityManager->getRepository(Position::class)

            ->findActivePositions();


        // Group by portfolio

        $portfolioData = [];

        $totals = [

            'totalInvested' => 0,

            'totalValue' => 0,

            'totalPL' => 0,

        ];


        foreach ($positions as $position) {

            $portfolio = $position->getPortfolio();

            $portfolioType = $portfolio->getAccountType();



            if (!isset($portfolioData[$portfolioType])) {

                $portfolioData[$portfolioType] = [

                    'positions' => [],

                    'totalValue' => 0,

                    'totalPL' => 0,

                ];

            }



            $portfolioData[$portfolioType]['positions'][] = $position;

            $portfolioData[$portfolioType]['totalValue'] += $position->getCurrentValue();

            $portfolioData[$portfolioType]['totalPL'] += $position->getProfitLoss();



            $totals['totalInvested'] += $position->getTotalSpent();

            $totals['totalValue'] += $position->getCurrentValue();

            $totals['totalPL'] += $position->getProfitLoss();

        }


        return $this->render('dashboard/index.html.twig', [

            'portfolioData' => $portfolioData,

            'totals' => $totals,

        ]);

    }

}
