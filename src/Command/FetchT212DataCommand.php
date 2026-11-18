<?php


namespace App\Command;


use App\Entity\Portfolio;
use App\Entity\Position;
use App\Entity\Transaction;
use App\Service\Trading212Service;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class FetchT212DataCommand extends Command
{
    protected static $defaultName = 'app:fetch-t212-data';

    // Minimum share change to be treated as a real trade
    private const MIN_TRADE_THRESHOLD = 0.001;

    private Trading212Service $trading212Service;
    private EntityManagerInterface $entityManager;

    public function __construct(Trading212Service $trading212Service, EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->trading212Service = $trading212Service;
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->setDescription('Fetch latest data from T212 and update the database (with transaction history)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('🔄 Fetching data from T212...');

        $accounts = [
            'INVEST' => 'getInvestPositions',
            'ISA' => 'getIsaPositions',
        ];

        foreach ($accounts as $type => $method) {
            $output->writeln("📊 Processing $type account...");

            try {
                $positions = $this->trading212Service->$method();
                $portfolio = $this->getOrCreatePortfolio($type);

                $apiTickers = [];
                foreach ($positions as $positionData) {
                    $ticker = $positionData->ticker ?? null;
                    if ($ticker) {
                        $apiTickers[$ticker] = $positionData;
                    }
                }

                $existingPositions = $portfolio->getPositions();

                foreach ($existingPositions as $existingPosition) {
                    $ticker = $existingPosition->getTicker();

                    if (isset($apiTickers[$ticker])) {
                        $this->updatePosition($existingPosition, $apiTickers[$ticker], $type, $output);
                        unset($apiTickers[$ticker]);
                    } else {
                        if ($existingPosition->isActive()) {
                            $this->recordFullSale($existingPosition, $type, $output);
                            $existingPosition->setIsActive(false);
                            $existingPosition->setSoldDate(new \DateTime());
                            $output->writeln("   💤 Marked as sold: {$ticker}");
                        }
                    }
                }

                foreach ($apiTickers as $ticker => $positionData) {
                    $this->createPosition($positionData, $portfolio, $type, $output);
                    $output->writeln("   ✨ New position: {$ticker}");
                }

                $portfolio->setLastUpdated(new \DateTime());
                $this->entityManager->flush();

                $output->writeln("✅ $type account updated successfully!");
            } catch (\Exception $e) {
                $output->writeln("❌ Error processing $type: " . $e->getMessage());
            }
        }

        $output->writeln('✅ Data fetch complete!');
        return Command::SUCCESS;
    }

    private function getOrCreatePortfolio(string $type): Portfolio
    {
        $portfolio = $this->entityManager->getRepository(Portfolio::class)
            ->findOneBy(['accountType' => $type]);

        if (!$portfolio) {
            $portfolio = new Portfolio();
            $portfolio->setAccountType($type);
            $portfolio->setAccountName($type . ' Account');
            $this->entityManager->persist($portfolio);
        }

        return $portfolio;
    }

    private function updatePosition(Position $position, $positionData, string $accountType, OutputInterface $output): void
    {
        $oldShares = $position->getShares() ?? 0;
        $newShares = $positionData->quantity ?? 0;

        // Calculate prices mathematically from API totals to bypass API glitches
        $totalSpent = $positionData->totalSpentGBP ?? 0;
        $currentValue = $positionData->currentValueGBP ?? 0;
        $profitLoss = $positionData->profitLossGBP ?? 0;

        if ($newShares > 0) {
            $avgPrice = $totalSpent / $newShares;
            $currentPrice = $currentValue / $newShares;
        } else {
            $avgPrice = $position->getAvgPrice();
            $currentPrice = $position->getCurrentPrice();
        }

        $profitLossPercent = $totalSpent > 0 ? ($profitLoss / $totalSpent) * 100 : 0;

         // REMOVED: Fast sync no longer records transactions to prevent blank fill_id duplicates. 
        // The backfill command handles this with accurate T212 data.

        $position->setShares($newShares);
        $position->setCurrentPrice($currentPrice);
        $position->setAvgPrice($avgPrice);
        $position->setTotalSpent($totalSpent);
        $position->setCurrentValue($currentValue);
        $position->setProfitLoss($profitLoss);
        $position->setProfitLossPercent($profitLossPercent);
        $position->setLastUpdated(new \DateTime());

        if (!$position->isActive()) {
            $position->setIsActive(true);
            $position->setSoldDate(null);
        }
    }

    private function recordSale(Position $position, float $sharesSold, float $sellPrice, string $accountType, OutputInterface $output): void
    {
        $avgPrice = $position->getAvgPrice() ?? 0;

        $totalValue = $sharesSold * $sellPrice;
        $costBasis = $sharesSold * $avgPrice;
        $realisedPl = $totalValue - $costBasis;
        $realisedPlPercent = $costBasis > 0 ? ($realisedPl / $costBasis) * 100 : 0;

        $tx = new Transaction();
        $tx->setPosition($position);
        $tx->setTicker($position->getTicker());
        $tx->setAccountType($accountType);
        $tx->setType(Transaction::TYPE_SELL);
        $tx->setShares($sharesSold);
        $tx->setPricePerShare($sellPrice);
        $tx->setTotalValue($totalValue);
        $tx->setAvgPriceAtTime($avgPrice);
        $tx->setRealisedPl($realisedPl);
        $tx->setRealisedPlPercent($realisedPlPercent);
        $tx->setTradeDate(new \DateTime());
        $tx->setSource(Transaction::SOURCE_API);

        $this->entityManager->persist($tx);

        $output->writeln(sprintf(
            "   💰 Sold %s × %s @ £%s (P/L: £%s)",
            number_format($sharesSold, 0),
            $position->getTicker(),
            number_format($sellPrice, 4),
            number_format($realisedPl, 2)
        ));
    }

    private function recordBuy(Position $position, float $sharesBought, float $buyPrice, string $accountType, OutputInterface $output): void
    {
        $avgPrice = $position->getAvgPrice() ?? 0;
        $totalValue = $sharesBought * $buyPrice;

        $tx = new Transaction();
        $tx->setPosition($position);
        $tx->setTicker($position->getTicker());
        $tx->setAccountType($accountType);
        $tx->setType(Transaction::TYPE_BUY);
        $tx->setShares($sharesBought);
        $tx->setPricePerShare($buyPrice);
        $tx->setTotalValue($totalValue);
        $tx->setAvgPriceAtTime($avgPrice);
        $tx->setTradeDate(new \DateTime());
        $tx->setSource(Transaction::SOURCE_API);

        $this->entityManager->persist($tx);

        $output->writeln(sprintf(
            "   🛒 Bought %s × %s @ £%s",
            number_format($sharesBought, 4),
            $position->getTicker(),
            number_format($buyPrice, 4)
        ));
    }

    private function recordFullSale(Position $position, string $accountType, OutputInterface $output): void
    {
        $sharesSold = $position->getShares() ?? 0;
        if ($sharesSold <= 0) {
            return;
        }
        $sellPrice = $position->getCurrentPrice() ?? 0;
        $this->recordSale($position, $sharesSold, $sellPrice, $accountType, $output);
    }

    private function createPosition($positionData, Portfolio $portfolio, string $accountType, OutputInterface $output): void
    {
        $position = new Position();

        $ticker = $positionData->ticker ?? 'UNKNOWN';
        $name = $positionData->name ?? $ticker;
        $quantity = $positionData->quantity ?? 0;

        $totalSpent = $positionData->totalSpentGBP ?? 0;
        $currentValue = $positionData->currentValueGBP ?? 0;
        $profitLoss = $positionData->profitLossGBP ?? 0;

        // Calculate prices mathematically from API totals
        if ($quantity > 0) {
            $avgPrice = $totalSpent / $quantity;
            $currentPrice = $currentValue / $quantity;
        } else {
            $avgPrice = 0;
            $currentPrice = 0;
        }

        $profitLossPercent = $totalSpent > 0 ? ($profitLoss / $totalSpent) * 100 : 0;

        $position->setTicker($ticker);
        $position->setName($name);
        $position->setShares($quantity);
        $position->setCurrentPrice($currentPrice);
        $position->setAvgPrice($avgPrice);
        $position->setTotalSpent($totalSpent);
        $position->setCurrentValue($currentValue);
        $position->setProfitLoss($profitLoss);
        $position->setProfitLossPercent($profitLossPercent);
        $position->setLastUpdated(new \DateTime());
        $position->setPortfolio($portfolio);
        $position->setIsActive(true);

        $this->entityManager->persist($position);

        // Record initial buy only if meaningful
        if (abs($quantity) >= self::MIN_TRADE_THRESHOLD) {
            $this->recordBuy($position, $quantity, $currentPrice, $accountType, $output);
        }
    }
}
