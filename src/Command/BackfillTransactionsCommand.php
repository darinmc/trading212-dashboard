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

class BackfillTransactionsCommand extends Command
{
    protected static $defaultName = 'app:backfill-transactions';

    private Trading212Service $trading212Service;
    private EntityManagerInterface $entityManager;

    private const TICKER_MAPPING = [
        'LAES_US_EQ' => 'LAES', 'TRIB_US_EQ' => 'TRIB', 'SRESl_EQ' => 'SRES',
        'KDNCl_EQ' => 'KDNC', 'PHGE_US_EQ' => 'HLSQ', 'CNSl_EQ' => 'CNS',
        'SMLPl_EQ' => 'SML', 'SANA_US_EQ' => 'SANA', 'HE1l_EQ' => 'HE1',
        'SNYR_US_EQ' => 'SNYR', 'CGTX_US_EQ' => 'CGTX', 'XTRl_EQ' => 'XTR',
        'GMM_US_EQ' => 'GMM', 'BHLl_EQ' => 'BHL', 'SBFM_US_EQ' => 'SBFM',
        'RNLSY_US_EQ' => 'RNLSY', 'PREMl_EQ' => 'PREM', 'WISA_US_EQ' => 'DVLT',
        'PTGEF_US_EQ' => 'ALP', 'FCAU_US_EQ' => 'STLA', 'ARTL_US_EQ' => 'ARTL',
        'MSMNl_EQ' => 'QHE', 'MET1l_EQ' => 'MET1', 'KODl_EQ' => 'KOD',
        'BSN_US_EQ' => 'EVTL', 'SYN1_US_EQ' => 'TOVX', 'IESl_EQ' => 'IES',
        'TEKl_EQ' => 'TEK', 'BYDDY_US_EQ' => 'BYDDY', 'AIRp_EQ' => 'AIR',
        'BSIFl_EQ' => 'BSIF', 'PSNl_EQ' => 'PSN', 'TRIGl_EQ' => 'TRIG',
        'CYSEl_EQ' => 'CYSE', 'KLWD1l_EQ' => 'KLWD', 'ORITl_EQ' => 'ORIT',
        'VTUl_EQ' => 'VTU', 'RNOp_EQ' => 'RNO', 'HSCS_US_EQ' => 'HSCS',
        'IKT_US_EQ' => 'IKT', 'QH_US_EQ' => 'QHE', 'FOGBl_EQ' => 'FOGB',
        'CHTEl_EQ' => 'CHTE', 'CEMXl_EQ' => 'CEMX', 'SWCl1_EQ' => 'SWC',
        'SEITl_EQ' => 'SEIT', 'NSANY_US_EQ' => 'NSANY', 'IVDA_US_EQ' => 'IVDA',
        'NPT_US_EQ' => 'NPT', 'MAYA_US_EQ' => 'GOW', 'GOW' => 'GOW', 'MAYA' => 'MAYA', 'MAYA' => 'MAYA', 'GLUXp_EQ' => 'GLUX',
        'NCLPl_EQ' => 'NCLP', 'QWTMl_EQ' => 'QWTM', 'WQTMl_EQ' => 'WQTM',
        'WRENl_EQ' => 'WREN', 'WTNRl_EQ' => 'WTNR', 'WBIOl_EQ' => 'WBIO',
        'WDEPl_EQ' => 'WDEP', 'WREEl_EQ' => 'WREE', 'BKCNl_EQ' => 'BKCN',
        'SMGBl_EQ' => 'SMGB', 'AGESl_EQ' => 'AGES', 'CHRGl_EQ' => 'CHRG',
        'INTLl_EQ' => 'INTL',
    ];

    public function __construct(Trading212Service $trading212Service, EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->trading212Service = $trading212Service;
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->setDescription('Incremental backfill of transaction history from T212');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('🔄 Starting incremental backfill...');

        // Determine the start date (latest transaction minus 4 days safety window)
        $latestDate = $this->getLatestTransactionDate();
        $fromDate = null;

        if ($latestDate) {
            $fromDate = (clone $latestDate)->modify('-4 days')->format('Y-m-d');
            $output->writeln("   📅 Latest DB transaction: " . $latestDate->format('Y-m-d'));
            $output->writeln("   📅 Fetching from API since: $fromDate (includes 4-day safety window)");
        } else {
            $output->writeln("   📅 No existing transactions found. Fetching full history.");
        }

        $accounts = [
            'INVEST' => 'getInvestOrders',
            'ISA' => 'getIsaOrders',
        ];

        $totalImported = 0;
        $totalSkipped = 0;

        foreach ($accounts as $type => $method) {
            $output->writeln("📊 Processing $type orders...");

            try {
                $orders = $this->trading212Service->$method($fromDate);
                $output->writeln("   ✅ Received " . count($orders) . " orders");

                if (empty($orders)) {
                    $output->writeln("   ⚠️  No orders returned for $type");
                    continue;
                }

                foreach ($orders as $order) {
                    $result = $this->importOrder($order, $type, $output);
                    if ($result === 'imported') {
                        $totalImported++;
                    } elseif ($result === 'skipped') {
                        $totalSkipped++;
                    }
                }

                $this->entityManager->flush();
                $output->writeln("✅ $type orders processed");

            } catch (\Exception $e) {
                $output->writeln("❌ Error processing $type: " . $e->getMessage());
            }
        }

        $output->writeln('');
        $output->writeln("📊 Backfill complete!");
        $output->writeln("   ✅ Imported: $totalImported");
        $output->writeln("   ⏭️  Skipped:  $totalSkipped");

        return Command::SUCCESS;
    }

    private function getLatestTransactionDate(): ?\DateTime
    {
        $result = $this->entityManager->createQueryBuilder()
            ->select('MAX(t.tradeDate)')
            ->from(Transaction::class, 't')
            ->getQuery()
            ->getSingleScalarResult();

        if (!$result) {
            return null;
        }

        return $result instanceof \DateTimeInterface
            ? $result
            : new \DateTime($result);
    }

    private function importOrder(array $order, string $accountType, OutputInterface $output): string
    {
        $orderData = $order['order'] ?? null;
        $fillData = $order['fill'] ?? null;

        if (!$orderData || !$fillData) {
            return 'skipped';
        }

        if (($orderData['status'] ?? '') !== 'FILLED') {
            return 'skipped';
        }

        $side = $orderData['side'] ?? '';
        if (!in_array($side, ['BUY', 'SELL'])) {
            return 'skipped';
        }

        $rawTicker = $orderData['instrument']['ticker'] ?? $orderData['ticker'] ?? null;
        $shares = abs($fillData['quantity'] ?? 0);
        $fillId = isset($fillData['id']) ? (string) $fillData['id'] : null;
        $orderId = isset($orderData['id']) ? (string) $orderData['id'] : null;
        $filledAt = $fillData['filledAt'] ?? $orderData['createdAt'] ?? null;

        if (!$rawTicker || $shares <= 0 || !$fillId) {
            return 'skipped';
        }

        if ($this->fillAlreadyImported($fillId)) {
            return 'skipped';
        }

        $walletImpact = $fillData['walletImpact'] ?? [];
        
        // If T212 didn't send a walletImpact (or netValue is null/0), it's a corporate action. Skip it.
        if (!isset($walletImpact['netValue']) || $walletImpact['netValue'] == 0) {
            return 'skipped';
        }

        $netValueGbp = abs($walletImpact['netValue']);
        $fxRate = $walletImpact['fxRate'] ?? null;
        $realisedPl = $walletImpact['realisedProfitLoss'] ?? null;
        $priceGbp = $shares > 0 ? ($netValueGbp / $shares) : 0;

        // Skip anything with a total value less than 1p (0.01)
        if ($netValueGbp < 0.01) {
            return 'skipped';
        }

        $standardTicker = self::TICKER_MAPPING[$rawTicker] ?? $rawTicker;
        $position = $this->findPosition($standardTicker, $accountType);

        $tx = new Transaction();
        $tx->setPosition($position);
        $tx->setTicker($standardTicker);
        $tx->setAccountType($accountType);
        $tx->setType($side);
        $tx->setShares($shares);
        $tx->setPricePerShare($priceGbp);
        $tx->setTotalValue($netValueGbp);
        $tx->setFillId($fillId);
        $tx->setOrderId($orderId);
        $tx->setFxRate($fxRate);
        $tx->setNetValueGbp($netValueGbp);

        if ($position) {
            $tx->setAvgPriceAtTime($position->getAvgPrice());
        }

        if ($side === 'SELL' && $realisedPl !== null) {
            $tx->setRealisedPl($realisedPl);
            if ($position && $position->getAvgPrice() > 0) {
                $costBasis = $shares * $position->getAvgPrice();
                $percent = $costBasis > 0 ? ($realisedPl / $costBasis) * 100 : 0;
                $tx->setRealisedPlPercent($percent);
            }
        }

        $tx->setTradeDate(new \DateTime($filledAt));
        $tx->setSource(Transaction::SOURCE_API);

        $this->entityManager->persist($tx);
        return 'imported';
    }

    private function fillAlreadyImported(string $fillId): bool
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(t.id)')
            ->from(Transaction::class, 't')
            ->where('t.fillId = :fillId')
            ->setParameter('fillId', $fillId);

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    private function findPosition(string $standardTicker, string $accountType): ?Position
    {
        $portfolio = $this->entityManager->getRepository(Portfolio::class)
            ->findOneBy(['accountType' => $accountType]);

        if (!$portfolio) {
            return null;
        }

        return $this->entityManager->getRepository(Position::class)
            ->findOneBy([
                'ticker' => $standardTicker,
                'portfolio' => $portfolio,
            ]);
    }
}
