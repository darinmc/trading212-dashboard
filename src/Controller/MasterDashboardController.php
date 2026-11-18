<?php

namespace App\Controller;

use App\Entity\Position;
use App\Entity\Portfolio;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MasterDashboardController extends AbstractController
{
        #[Route('/', name: 'app_master_dashboard')]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $sort = $request->query->get('sort', 'ticker');
        $dir = $request->query->get('dir', 'ASC');

        // Get all portfolios
        $portfolioRepository = $entityManager->getRepository(Portfolio::class);
        $portfolios = $portfolioRepository->findAll();

        // Get all active (not sold) positions
        $positionRepository = $entityManager->getRepository(Position::class);
        $allPositions = $positionRepository->findActivePositions();

        // Group positions by portfolio
        $portfolioData = [];
        $totals = [];

        foreach ($portfolios as $portfolio) {
            $portfolioType = $portfolio->getAccountType();
            $portfolioName = $portfolio->getAccountName();

            $portfolioData[$portfolioType] = [
                'name' => $portfolioName,
                'positions' => [],
                'totalValue' => 0,
                'totalPL' => 0,
                'totalPLPercent' => 0,
                'totalInvested' => 0,
            ];

            $totals[$portfolioType] = [
                'totalValue' => 0,
                'totalPL' => 0,
                'totalPLPercent' => 0,
                'totalInvested' => 0,
            ];
        }

        foreach ($allPositions as $position) {
            $portfolio = $position->getPortfolio();
            if (!$portfolio) continue;

            $portfolioType = $portfolio->getAccountType();

            if (!isset($portfolioData[$portfolioType])) {
                $portfolioData[$portfolioType] = [
                    'name' => $portfolio->getAccountName(),
                    'positions' => [],
                    'totalValue' => 0,
                    'totalPL' => 0,
                    'totalPLPercent' => 0,
                    'totalInvested' => 0,
                ];
            }

            $portfolioData[$portfolioType]['positions'][] = $position;
            $portfolioData[$portfolioType]['totalValue'] += $position->getCurrentValue();
            $portfolioData[$portfolioType]['totalPL'] += $position->getProfitLoss();
            $portfolioData[$portfolioType]['totalInvested'] += $position->getTotalSpent();

            $totals[$portfolioType]['totalValue'] += $position->getCurrentValue();
            $totals[$portfolioType]['totalPL'] += $position->getProfitLoss();
            $totals[$portfolioType]['totalInvested'] += $position->getTotalSpent();
        }

        // Calculate P/L percentages
        foreach ($totals as $type => $data) {
            $totalInvested = $data['totalInvested'];
            $totals[$type]['totalPLPercent'] = $totalInvested > 0 ? ($data['totalPL'] / $totalInvested) * 100 : 0;
        }

        // Create a flat list of all unique tickers with notes and flags
        $allTickers = [];
        $tickerNotes = [];
        $tickerImportant = [];
        $tickerCheck = [];
        $tickerFinancials = [];

        foreach ($portfolioData as $type => $data) {
            foreach ($data['positions'] as $position) {
                $ticker = $position->getTicker();
                if (!in_array($ticker, $allTickers)) {
                    $allTickers[] = $ticker;
                    $tickerNotes[$ticker] = $position->getNote();
                    $tickerImportant[$ticker] = $position->getImportant();
                    $tickerCheck[$ticker] = $position->getCheckFlag();
                    $tickerFinancials[$ticker] = $position->getFinancials();
                }
            }
        }

        // Sort the tickers based on the requested column
        if ($sort === 'ticker') {
            sort($allTickers, SORT_STRING | SORT_FLAG_CASE);
        } elseif ($sort === 'price') {
            usort($allTickers, function($a, $b) use ($portfolioData) {
                $priceA = $this->findCurrentPrice($portfolioData, $a);
                $priceB = $this->findCurrentPrice($portfolioData, $b);
                return $priceA <=> $priceB;
            });
        } elseif ($sort === 'invest_pl') {
            usort($allTickers, function($a, $b) use ($portfolioData) {
                $plA = $this->findPL($portfolioData, 'INVEST', $a);
                $plB = $this->findPL($portfolioData, 'INVEST', $b);
                return $plA <=> $plB;
            });
        } elseif ($sort === 'isa_pl') {
            usort($allTickers, function($a, $b) use ($portfolioData) {
                $plA = $this->findPL($portfolioData, 'ISA', $a);
                $plB = $this->findPL($portfolioData, 'ISA', $b);
                return $plA <=> $plB;
            });
        } elseif ($sort === 'sipp_pl') {
            usort($allTickers, function($a, $b) use ($portfolioData) {
                $plA = $this->findPL($portfolioData, 'SIPP', $a);
                $plB = $this->findPL($portfolioData, 'SIPP', $b);
                return $plA <=> $plB;
            });
        } elseif ($sort === 'invest_pl_percent') {
            usort($allTickers, function($a, $b) use ($portfolioData) {
                $plA = $this->findPLPercent($portfolioData, 'INVEST', $a);
                $plB = $this->findPLPercent($portfolioData, 'INVEST', $b);
                return $plA <=> $plB;
            });
        } elseif ($sort === 'isa_pl_percent') {
            usort($allTickers, function($a, $b) use ($portfolioData) {
                $plA = $this->findPLPercent($portfolioData, 'ISA', $a);
                $plB = $this->findPLPercent($portfolioData, 'ISA', $b);
                return $plA <=> $plB;
            });
        } elseif ($sort === 'sipp_pl_percent') {
            usort($allTickers, function($a, $b) use ($portfolioData) {
                $plA = $this->findPLPercent($portfolioData, 'SIPP', $a);
                $plB = $this->findPLPercent($portfolioData, 'SIPP', $b);
                return $plA <=> $plB;
            });
        }

        if ($dir === 'DESC') {
            $allTickers = array_reverse($allTickers);
        }

        return $this->render('master_dashboard/index.html.twig', [
            'portfolioData' => $portfolioData,
            'totals' => $totals,
            'allTickers' => $allTickers,
            'tickerNotes' => $tickerNotes,
            'tickerImportant' => $tickerImportant,
            'tickerCheck' => $tickerCheck,
            'tickerFinancials' => $tickerFinancials,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    private function findCurrentPrice(array $portfolioData, string $ticker): float
    {
        foreach ($portfolioData as $type => $data) {
            foreach ($data['positions'] as $position) {
                if ($position->getTicker() === $ticker) {
                    return $position->getCurrentPrice();
                }
            }
        }
        return 0;
    }

    private function findPL(array $portfolioData, string $accountType, string $ticker): float
    {
        if (!isset($portfolioData[$accountType])) {
            return 0;
        }
        foreach ($portfolioData[$accountType]['positions'] as $position) {
            if ($position->getTicker() === $ticker) {
                return $position->getProfitLoss();
            }
        }
        return 0;
    }

    private function findPLPercent(array $portfolioData, string $accountType, string $ticker): float
    {
        if (!isset($portfolioData[$accountType])) {
            return 0;
        }
        foreach ($portfolioData[$accountType]['positions'] as $position) {
            if ($position->getTicker() === $ticker) {
                return $position->getProfitLossPercent();
            }
        }
        return 0;
    }
}
