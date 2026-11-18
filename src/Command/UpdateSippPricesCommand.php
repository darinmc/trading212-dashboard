<?php

namespace App\Command;

use App\Entity\Portfolio;
use App\Entity\Position;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;

class UpdateSippPricesCommand extends Command
{
    protected static $defaultName = 'app:update-sipp-prices';

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->setDescription('Update SIPP prices using T212 DB prices first, then Yahoo Finance fallback');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('🔄 Fetching latest prices for SIPP...');

        // 1. Build a dictionary of prices from Invest and ISA
        $t212Prices = [];
        $t212Portfolios = $this->entityManager->getRepository(Portfolio::class)
            ->findBy(['accountType' => ['INVEST', 'ISA']]);

        foreach ($t212Portfolios as $portfolio) {
            $positions = $this->entityManager->getRepository(Position::class)
                ->findBy(['portfolio' => $portfolio, 'isActive' => true]);
            
            foreach ($positions as $pos) {
                if ($pos->getCurrentPrice() > 0) {
                    $t212Prices[$pos->getTicker()] = $pos->getCurrentPrice();
                }
            }
        }

        $output->writeln("   📊 Loaded " . count($t212Prices) . " live prices from T212 accounts.");

        // 2. Loop through SIPP positions
        $sippPortfolio = $this->entityManager->getRepository(Portfolio::class)
            ->findOneBy(['accountType' => 'SIPP']);

        if (!$sippPortfolio) {
            $output->writeln('❌ SIPP portfolio not found.');
            return Command::FAILURE;
        }

        $sippPositions = $this->entityManager->getRepository(Position::class)
            ->findBy(['portfolio' => $sippPortfolio, 'isActive' => true]);

        if (empty($sippPositions)) {
            $output->writeln('⚠️ No active SIPP positions found.');
            return Command::SUCCESS;
        }

        $httpClient = HttpClient::create();
        $updatedCount = 0;

        foreach ($sippPositions as $position) {
            $ticker = $position->getTicker();
            $price = null;
            $source = '';

            // Check T212 prices first
            if (isset($t212Prices[$ticker])) {
                $price = $t212Prices[$ticker];
                $source = 'T212 DB';
            } else {
                // Fallback to Yahoo Finance
                $symbolsToTry = [$ticker . '.L', $ticker];

                foreach ($symbolsToTry as $symbol) {
                    try {
                        $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}?interval=1d";
                        $response = $httpClient->request('GET', $url, [
                            'headers' => ['User-Agent' => 'Mozilla/5.0']
                        ]);

                        if ($response->getStatusCode() === 200) {
                            $data = $response->toArray();
                            $result = $data['chart']['result'][0] ?? null;
                            
                            if ($result && isset($result['meta']['regularMarketPrice'])) {
                                $price = (float) $result['meta']['regularMarketPrice'];
                                
                                // Convert pence to GBP for UK stocks
                                if (str_ends_with($symbol, '.L')) {
                                    $price = $price / 100;
                                }
                                
                                $source = 'Yahoo (' . $symbol . ')';
                                break;
                            }
                        }
                    } catch (\Exception $e) {
                        // Silently fail and try the next symbol
                    }
                }
            }

            // Apply the price if found
            if ($price !== null) {
                $shares = $position->getShares();
                $newCurrentValue = $shares * $price;
                $profitLoss = $newCurrentValue - $position->getTotalSpent();
                $profitLossPercent = $position->getTotalSpent() > 0 ? ($profitLoss / $position->getTotalSpent()) * 100 : 0;

                $position->setCurrentPrice($price);
                $position->setCurrentValue($newCurrentValue);
                $position->setProfitLoss($profitLoss);
                $position->setProfitLossPercent($profitLossPercent);
                $position->setLastUpdated(new \DateTime());

                $output->writeln(sprintf("   ✅ %-5s : £%.4f (via %s)", $ticker, $price, $source));
                $updatedCount++;
            } else {
                $output->writeln("   ❌ $ticker : Could not find price anywhere.");
            }
        }

        $this->entityManager->flush();
        $output->writeln("📊 Updated $updatedCount SIPP positions.");

        return Command::SUCCESS;
    }
}
