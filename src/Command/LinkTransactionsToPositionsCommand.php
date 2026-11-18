<?php

namespace App\Command;

use App\Entity\Portfolio;
use App\Entity\Position;
use App\Entity\Transaction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LinkTransactionsToPositionsCommand extends Command
{
    protected static $defaultName = 'app:link-transactions';

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
        'NPT_US_EQ' => 'NPT', 'MAYA_US_EQ' => 'MAYA', 'GLUXp_EQ' => 'GLUX',
        'NCLPl_EQ' => 'NCLP', 'QWTMl_EQ' => 'QWTM', 'WQTMl_EQ' => 'WQTM',
        'WRENl_EQ' => 'WREN', 'WTNRl_EQ' => 'WTNR', 'WBIOl_EQ' => 'WBIO',
        'WDEPl_EQ' => 'WDEP', 'WREEl_EQ' => 'WREE', 'BKCNl_EQ' => 'BKCN',
        'SMGBl_EQ' => 'SMGB', 'AGESl_EQ' => 'AGES', 'CHRGl_EQ' => 'CHRG',
        'INTLl_EQ' => 'INTL',
    ];

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->setDescription('Link transactions to positions using T212 ticker mappings');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('🔗 Linking transactions to positions...');

        $transactions = $this->entityManager->getRepository(Transaction::class)
            ->findBy(['position' => null]);

        $output->writeln("   Found " . count($transactions) . " unlinked transactions");

        $linked = 0;
        $notFound = 0;
        $notFoundTickers = [];

        foreach ($transactions as $tx) {
            $rawTicker = $tx->getTicker();
            $accountType = $tx->getAccountType();
            $standardTicker = self::TICKER_MAPPING[$rawTicker] ?? $rawTicker;

            $portfolio = $this->entityManager->getRepository(Portfolio::class)
                ->findOneBy(['accountType' => $accountType]);

            if (!$portfolio) {
                continue;
            }

            // Step 1: Try same account, mapped ticker
            $position = $this->entityManager->getRepository(Position::class)
                ->findOneBy([
                    'ticker' => $standardTicker,
                    'portfolio' => $portfolio,
                ]);

            // Step 2: If not found, try across ALL accounts
            if (!$position) {
                $position = $this->entityManager->getRepository(Position::class)
                    ->findOneBy(['ticker' => $standardTicker]);
            }

            // Step 3: If still not found, try by raw ticker
            if (!$position) {
                $position = $this->entityManager->getRepository(Position::class)
                    ->findOneBy(['ticker' => $rawTicker]);
            }

            if ($position) {
                $tx->setPosition($position);
                $linked++;
            } else {
                $notFound++;
                $notFoundTickers[$standardTicker] = ($notFoundTickers[$standardTicker] ?? 0) + 1;
            }
        }

        $this->entityManager->flush();

        $output->writeln('');
        $output->writeln("📊 Link complete:");
        $output->writeln("   ✅ Linked:    $linked");
        $output->writeln("   ❌ Not found: $notFound");

        if (!empty($notFoundTickers)) {
            $output->writeln('');
            $output->writeln("   Tickers with no position record:");
            arsort($notFoundTickers);
            foreach (array_slice($notFoundTickers, 0, 20) as $ticker => $count) {
                $output->writeln("      $ticker: $count");
            }
        }

        return Command::SUCCESS;
    }
}
