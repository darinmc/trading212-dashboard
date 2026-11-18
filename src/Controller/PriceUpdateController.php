<?php

namespace App\Controller;

use App\Entity\Position;
use App\Entity\Portfolio;
use App\Service\Trading212Service;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PriceUpdateController extends AbstractController
{
    #[Route('/sync-t212', name: 'app_sync_t212', methods: ['POST'])]
    public function syncT212(EntityManagerInterface $entityManager, Trading212Service $trading212Service, HttpClientInterface $httpClient): Response
    {
        $updated = 0;
        $errors = [];
        $yahooTickers = [];
        $removedTotal = 0;

        // Get portfolios
        $portfolioRepository = $entityManager->getRepository(Portfolio::class);
        $investPortfolio = $portfolioRepository->findOneBy(['accountType' => 'INVEST']);
        $isaPortfolio = $portfolioRepository->findOneBy(['accountType' => 'ISA']);
        $sippPortfolio = $portfolioRepository->findOneBy(['accountType' => 'SIPP']);

        if (!$investPortfolio) {
            $errors[] = 'Invest portfolio not found';
        }

        if (!$isaPortfolio) {
            $errors[] = 'ISA portfolio not found';
        }

        // Sync Invest positions with retry
        if ($investPortfolio) {
            try {
                $investPositions = $this->retryCall(function() use ($trading212Service) {
                    return $trading212Service->getInvestPositions();
                }, 3, 2);

                $apiTickers = [];
                foreach ($investPositions as $apiPosition) {
                    $this->updateOrCreatePosition($entityManager, $apiPosition, $investPortfolio);
                    $apiTickers[] = $apiPosition->ticker;
                    $updated++;
                }

                // Clean up orphaned positions (no longer in T212)
                $removedInvest = $this->cleanupOrphanPositions($entityManager, $apiTickers, $investPortfolio);
                if ($removedInvest > 0) {
                    $removedTotal += $removedInvest;
                }
            } catch (\Exception $e) {
                $errors[] = 'Invest: ' . $e->getMessage();
            }
        }

        // Sync ISA positions with retry
        if ($isaPortfolio) {
            try {
                $isaPositions = $this->retryCall(function() use ($trading212Service) {
                    return $trading212Service->getIsaPositions();
                }, 3, 2);

                $apiTickers = [];
                foreach ($isaPositions as $apiPosition) {
                    $this->updateOrCreatePosition($entityManager, $apiPosition, $isaPortfolio);
                    $apiTickers[] = $apiPosition->ticker;
                    $updated++;
                }

                // Clean up orphaned positions (no longer in T212)
                $removedIsa = $this->cleanupOrphanPositions($entityManager, $apiTickers, $isaPortfolio);
                if ($removedIsa > 0) {
                    $removedTotal += $removedIsa;
                }
            } catch (\Exception $e) {
                $errors[] = 'ISA: ' . $e->getMessage();
            }
        }

        // Update SIPP positions - first from Invest/ISA, then fallback to Yahoo Finance
        if ($sippPortfolio) {
            $sippPositions = $entityManager->getRepository(Position::class)->findBy(['portfolio' => $sippPortfolio]);

            foreach ($sippPositions as $sippPosition) {
                $ticker = $sippPosition->getTicker();

                // First, try to find the same ticker in Invest or ISA
                $sourcePosition = null;
                if ($investPortfolio) {
                    $sourcePosition = $entityManager->getRepository(Position::class)->findOneBy([
                        'ticker' => $ticker,
                        'portfolio' => $investPortfolio,
                    ]);
                }
                if (!$sourcePosition && $isaPortfolio) {
                    $sourcePosition = $entityManager->getRepository(Position::class)->findOneBy([
                        'ticker' => $ticker,
                        'portfolio' => $isaPortfolio,
                    ]);
                }

                if ($sourcePosition) {
                    // Found in Invest/ISA - use T212 data
                    $currentPrice = $sourcePosition->getCurrentPrice();
                    $sippPosition->setCurrentPrice($currentPrice);

                    // Recalculate P/L
                    $shares = $sippPosition->getShares();
                    $avgPrice = $sippPosition->getAvgPrice();
                    $totalSpent = $shares * $avgPrice;
                    $currentValue = $shares * $currentPrice;
                    $profitLoss = $currentValue - $totalSpent;
                    $profitLossPercent = ($totalSpent > 0) ? ($profitLoss / $totalSpent) * 100 : 0;

                    $sippPosition->setTotalSpent($totalSpent);
                    $sippPosition->setCurrentValue($currentValue);
                    $sippPosition->setProfitLoss($profitLoss);
                    $sippPosition->setProfitLossPercent($profitLossPercent);
                    $sippPosition->setLastUpdated(new \DateTime());

                    $updated++;
                } else {
                    // Not found in Invest/ISA - fallback to Yahoo Finance
                    try {
                        $response = $httpClient->request('GET', 'https://query1.finance.yahoo.com/v8/finance/chart/' . urlencode($ticker) . '?interval=1d&range=1d');
                        $data = $response->toArray();

                        if (isset($data['chart']['result'][0]['meta']['regularMarketPrice'])) {
                            $currentPriceRaw = $data['chart']['result'][0]['meta']['regularMarketPrice'];
                            $currency = $data['chart']['result'][0]['meta']['currency'] ?? 'USD';

                            // Convert to GBP
                            if ($currency === 'GBp' || $currency === 'GBX') {
                                $currentPrice = $currentPriceRaw / 100;
                            } elseif ($currency === 'USD') {
                                $fxResponse = $httpClient->request('GET', 'https://query1.finance.yahoo.com/v8/finance/chart/GBPUSD=X?interval=1d&range=1d');
                                $fxData = $fxResponse->toArray();
                                $usdToGbp = $fxData['chart']['result'][0]['meta']['regularMarketPrice'] ?? 0.75;
                                $currentPrice = $currentPriceRaw * (1 / $usdToGbp);
                            } else {
                                $currentPrice = $currentPriceRaw;
                            }

                            $sippPosition->setCurrentPrice($currentPrice);

                            // Recalculate P/L
                            $shares = $sippPosition->getShares();
                            $avgPrice = $sippPosition->getAvgPrice();
                            $totalSpent = $shares * $avgPrice;
                            $currentValue = $shares * $currentPrice;
                            $profitLoss = $currentValue - $totalSpent;
                            $profitLossPercent = ($totalSpent > 0) ? ($profitLoss / $totalSpent) * 100 : 0;

                            $sippPosition->setTotalSpent($totalSpent);
                            $sippPosition->setCurrentValue($currentValue);
                            $sippPosition->setProfitLoss($profitLoss);
                            $sippPosition->setProfitLossPercent($profitLossPercent);
                            $sippPosition->setLastUpdated(new \DateTime());

                            $yahooTickers[] = $ticker;
                            $updated++;
                        }
                    } catch (\Exception $e) {
                        $errors[] = $ticker . ': ' . $e->getMessage();
                    }
                }
            }
        }

        $entityManager->flush();

        $successMsg = "Synced {$updated} positions from T212.";
        if ($removedTotal > 0) {
            $successMsg .= " Removed {$removedTotal} orphaned position(s).";
        }
        if (!empty($yahooTickers)) {
            $successMsg .= " SIPP prices updated from Yahoo Finance for: " . implode(', ', $yahooTickers);
        }
        $this->addFlash('success', $successMsg);

        if (!empty($errors)) {
            $this->addFlash('error', "Errors: " . implode(', ', $errors));
        }

        return $this->redirectToRoute('app_master_dashboard');
    }

    #[Route('/update-prices', name: 'app_update_prices', methods: ['POST'])]
    public function updatePrices(EntityManagerInterface $entityManager, HttpClientInterface $httpClient): Response
    {
        $positions = $entityManager->getRepository(Position::class)->findAll();

        $updated = 0;
        $errors = [];

        foreach ($positions as $position) {
            $ticker = $position->getTicker();

            if (empty($ticker)) {
                continue;
            }

            try {
                $response = $httpClient->request('GET', 'https://query1.finance.yahoo.com/v8/finance/chart/' . urlencode($ticker) . '?interval=1d&range=1d');
                $data = $response->toArray();

                if (isset($data['chart']['result'][0]['meta']['regularMarketPrice'])) {
                    $currentPrice = $data['chart']['result'][0]['meta']['regularMarketPrice'];

                    $position->setCurrentPrice($currentPrice);
                    $position->setLastUpdated(new \DateTime());

                    $shares = $position->getShares();
                    $avgPrice = $position->getAvgPrice();
                    $totalSpent = $shares * $avgPrice;
                    $currentValue = $shares * $currentPrice;
                    $profitLoss = $currentValue - $totalSpent;
                    $profitLossPercent = ($totalSpent > 0) ? ($profitLoss / $totalSpent) * 100 : 0;

                    $position->setTotalSpent($totalSpent);
                    $position->setCurrentValue($currentValue);
                    $position->setProfitLoss($profitLoss);
                    $position->setProfitLossPercent($profitLossPercent);

                    $updated++;
                } else {
                    $errors[] = $ticker . ': No price data found';
                }
            } catch (\Exception $e) {
                $errors[] = $ticker . ': ' . $e->getMessage();
            }
        }

        $entityManager->flush();

        $this->addFlash('success', "Updated prices for {$updated} positions.");
        if (!empty($errors)) {
            $this->addFlash('error', "Errors: " . implode(', ', $errors));
        }

        return $this->redirectToRoute('app_master_dashboard');
    }

    private function retryCall(callable $callback, int $maxRetries = 3, int $delaySeconds = 2): array
    {
        $attempt = 0;
        while ($attempt < $maxRetries) {
            try {
                return $callback();
            } catch (\Exception $e) {
                $attempt++;
                if ($attempt >= $maxRetries) {
                    throw $e;
                }
                sleep($delaySeconds);
            }
        }

        throw new \Exception('Max retries exceeded');
    }

    private function updateOrCreatePosition(EntityManagerInterface $entityManager, object $apiPosition, Portfolio $portfolio): void
    {
        $positionRepository = $entityManager->getRepository(Position::class);

        // First, check if a position exists with the mapped ticker
        $existingPosition = $positionRepository->findOneBy([
            'ticker' => $apiPosition->ticker,
            'portfolio' => $portfolio,
        ]);

        // If not found, check if a position exists with the T212 original ticker
        if (!$existingPosition && isset($apiPosition->t212Ticker)) {
            $existingPosition = $positionRepository->findOneBy([
                'ticker' => $apiPosition->t212Ticker,
                'portfolio' => $portfolio,
            ]);

            // If found, update the ticker to the mapped one
            if ($existingPosition) {
                $existingPosition->setTicker($apiPosition->ticker);
            }
        }

        if ($existingPosition) {
            // Update existing position
            $existingPosition->setName($apiPosition->name);
            $existingPosition->setShares((int)$apiPosition->quantity);
            $existingPosition->setAvgPrice($apiPosition->avgPriceGBP);
            $existingPosition->setCurrentPrice($apiPosition->currentPriceGBP);
            $existingPosition->setTotalSpent($apiPosition->totalSpentGBP);
            $existingPosition->setCurrentValue($apiPosition->currentValueGBP);
            $existingPosition->setProfitLoss($apiPosition->profitLossGBP);
            $existingPosition->setProfitLossPercent($apiPosition->totalSpentGBP > 0 ? ($apiPosition->profitLossGBP / $apiPosition->totalSpentGBP) * 100 : 0);
            $existingPosition->setLastUpdated(new \DateTime());
        } else {
            // Create new position
            $position = new Position();
            $position->setTicker($apiPosition->ticker);
            $position->setName($apiPosition->name);
            $position->setShares((int)$apiPosition->quantity);
            $position->setAvgPrice($apiPosition->avgPriceGBP);
            $position->setCurrentPrice($apiPosition->currentPriceGBP);
            $position->setTotalSpent($apiPosition->totalSpentGBP);
            $position->setCurrentValue($apiPosition->currentValueGBP);
            $position->setProfitLoss($apiPosition->profitLossGBP);
            $position->setProfitLossPercent($apiPosition->totalSpentGBP > 0 ? ($apiPosition->profitLossGBP / $apiPosition->totalSpentGBP) * 100 : 0);
            $position->setLastUpdated(new \DateTime());
            $position->setPortfolio($portfolio);

            $entityManager->persist($position);
        }
    }

    /**
     * Delete positions from the database that no longer exist in T212
     */
    private function cleanupOrphanPositions(EntityManagerInterface $entityManager, array $apiTickers, Portfolio $portfolio): int
    {
        // Safety: If the API returned no tickers, skip cleanup (likely a network failure)
        if (empty($apiTickers)) {
            return 0;
        }

        $deleted = 0;
        $dbPositions = $entityManager->getRepository(Position::class)->findBy(['portfolio' => $portfolio]);

        foreach ($dbPositions as $dbPosition) {
            if (!in_array($dbPosition->getTicker(), $apiTickers)) {
                $entityManager->remove($dbPosition);
                $deleted++;
            }
        }

        return $deleted;
    }
}
