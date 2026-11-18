<?php

namespace App\Controller;

use App\Entity\Transaction;
use App\Repository\TransactionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;

class TradeHistoryController extends AbstractController
{
    private const TICKER_DISPLAY_MAP = [
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
        'NPT_US_EQ' => 'NPT', 'MAYA_US_EQ' => 'GOW', 'GLUXp_EQ' => 'GLUX',
        'NCLPl_EQ' => 'NCLP', 'QWTMl_EQ' => 'QWTM', 'WQTMl_EQ' => 'WQTM',
        'WRENl_EQ' => 'WREN', 'WTNRl_EQ' => 'WTNR', 'WBIOl_EQ' => 'WBIO',
        'WDEPl_EQ' => 'WDEP', 'WREEl_EQ' => 'WREE', 'BKCNl_EQ' => 'BKCN',
        'SMGBl_EQ' => 'SMGB', 'AGESl_EQ' => 'AGES', 'CHRGl_EQ' => 'CHRG',
        'INTLl_EQ' => 'INTL',
    ];

    #[Route('/trade-history', name: 'app_trade_history')]
    public function index(Request $request, TransactionRepository $repo): Response
    {
        $account = $request->query->get('account');
        $type = $request->query->get('type');
        $ticker = $request->query->get('ticker');
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');
        $sort = $request->query->get('sort', 'date');
        $dir = $request->query->get('dir', 'DESC');
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 50;

        $taxYear = $request->query->get('tax_year');
        $defaultedToCurrent = false;
        if ($taxYear === null && !$dateFrom && !$dateTo) {
            $taxYear = $repo->getCurrentTaxYear();
            $defaultedToCurrent = true;
        }

        $result = $repo->findFiltered(
            $account ?: null,
            $type ?: null,
            $ticker ?: null,
            $taxYear ?: null,
            $dateFrom ?: null,
            $dateTo ?: null,
            $sort,
            $dir,
            $page,
            $perPage
        );

        $items = $result['items'];
        $total = $result['total'];
        $totalPages = max(1, (int) ceil($total / $perPage));

        // Prepare display tickers
        $itemsDisplay = [];
        foreach ($items as $tx) {
            $rawTicker = $tx->getTicker();
            if ($tx->getPosition()) {
                $displayTicker = $tx->getPosition()->getTicker();
            } elseif (isset(self::TICKER_DISPLAY_MAP[$rawTicker])) {
                $displayTicker = self::TICKER_DISPLAY_MAP[$rawTicker];
            } else {
                $displayTicker = $this->stripSuffix($rawTicker);
            }
            $itemsDisplay[] = [
                'tx' => $tx,
                'displayTicker' => $displayTicker,
            ];
        }

        $taxYearSummary = null;
        if ($taxYear) {
            $taxYearSummary = $repo->getTaxYearSummary($taxYear);
        }

        $availableTaxYears = $repo->getAvailableTaxYears();
        $pageSummary = $this->calculatePageSummary($items);

        return $this->render('trade_history/index.html.twig', [
            'items' => $itemsDisplay,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'perPage' => $perPage,
            'account' => $account,
            'type' => $type,
            'ticker' => $ticker,
            'taxYear' => $taxYear,
            'defaultedToCurrent' => $defaultedToCurrent,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'sort' => $sort,
            'dir' => $dir,
            'taxYearSummary' => $taxYearSummary,
            'availableTaxYears' => $availableTaxYears,
            'pageSummary' => $pageSummary,
        ]);
    }

    #[Route('/trade-history/refresh', name: 'app_trade_history_refresh', methods: ['POST'])]
    public function refresh(Request $request): Response
    {
        $projectDir = $this->getParameter('kernel.project_dir');
        
        $commands = [
            'app:fetch-t212-data',
            'app:backfill-transactions',
	    'app:update-sipp-prices',
        ];
        
        $allSuccessful = true;
        $errors = [];
        $importedCount = 0;
        
        foreach ($commands as $command) {
            $process = new Process(['php', 'bin/console', $command]);
            $process->setWorkingDirectory($projectDir);
            $process->setTimeout(300);
            $process->run();
            
            if (!$process->isSuccessful()) {
                $allSuccessful = false;
                $errors[] = $command . ': ' . substr($process->getErrorOutput(), 0, 200);
                break;
            }

            // Extract imported count from backfill output
            if ($command === 'app:backfill-transactions') {
                $output = $process->getOutput();
                if (preg_match('/✅ Imported:\s+(\d+)/', $output, $matches)) {
                    $importedCount = (int)$matches[1];
                }
            }
        }
        
        if ($allSuccessful) {
            if ($importedCount > 0) {
                $this->addFlash('success', "✅ Refresh complete! Imported {$importedCount} new transactions.");
            } else {
                $this->addFlash('success', '✅ Refresh complete! No new transactions found.');
            }
        } else {
            $this->addFlash('error', '❌ Refresh failed: ' . implode(' | ', $errors));
        }
        
        // Redirect back to where they came from
        $referer = $request->headers->get('referer');
        if ($referer) {
            return $this->redirect($referer);
        }
        
        return $this->redirectToRoute('app_master_dashboard');
    }

    private function stripSuffix(string $ticker): string
    {
        $ticker = preg_replace('/_US_EQ$/', '', $ticker);
        $ticker = preg_replace('/l_EQ$/', '', $ticker);
        $ticker = preg_replace('/p_EQ$/', '', $ticker);
        return $ticker;
    }

    private function calculatePageSummary(array $items): array
    {
        $summary = [
            'buys' => 0,
            'sells' => 0,
            'buyValue' => 0.0,
            'sellValue' => 0.0,
            'realisedPl' => 0.0,
        ];

        foreach ($items as $tx) {
            if ($tx->getType() === Transaction::TYPE_BUY) {
                $summary['buys']++;
                $summary['buyValue'] += $tx->getTotalValue() ?? 0;
            } else {
                $summary['sells']++;
                $summary['sellValue'] += $tx->getTotalValue() ?? 0;
                $summary['realisedPl'] += $tx->getRealisedPl() ?? 0;
            }
        }

        return $summary;
    }
}
