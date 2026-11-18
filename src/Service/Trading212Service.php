<?php

namespace App\Service;

use MarekSkopal\Trading212\Trading212;
use MarekSkopal\Trading212\Config\Config;

class Trading212Service
{
    private Trading212 $investClient;
    private Trading212 $isaClient;
    private Trading212 $sippClient;

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
        'NPT_US_EQ' => 'NPT', 'MAYA_US_EQ' => 'GOW', 'GOW' => 'GOW', 'MAYA' => 'MAYA', 'MAYA' => 'MAYA', 'MAYA' => 'MAYA', 'GLUXp_EQ' => 'GLUX',
        'NCLPl_EQ' => 'NCLP', 'QWTMl_EQ' => 'QWTM', 'WQTMl_EQ' => 'WQTM',
        'WRENl_EQ' => 'WREN', 'WTNRl_EQ' => 'WTNR', 'WBIOl_EQ' => 'WBIO',
        'WDEPl_EQ' => 'WDEP', 'WREEl_EQ' => 'WREE', 'BKCNl_EQ' => 'BKCN',
        'SMGBl_EQ' => 'SMGB', 'AGESl_EQ' => 'AGES', 'CHRGl_EQ' => 'CHRG',
        'INTLl_EQ' => 'INTL',
    ];

    public function __construct(string $investApiKey, string $investSecret, string $isaApiKey, string $isaSecret, string $sippApiKey, string $sippSecret)
    {
        $investConfig = new Config($investApiKey, $investSecret);
        $isaConfig = new Config($isaApiKey, $isaSecret);
        $sippConfig = new Config($sippApiKey, $sippSecret);

        $this->investClient = new Trading212($investConfig);
        $this->isaClient = new Trading212($isaConfig);
        $this->sippClient = new Trading212($sippConfig);
    }

    public function getInvestPositions(): array
    {
        $client = $this->investClient->getPersonalPortfolio();
        $reflection = new \ReflectionClass($client);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $httpClient = $property->getValue($client);

        $response = $httpClient->get('/api/v0/equity/positions', []);
        $data = json_decode($response, true);

        return $this->parsePositions($data);
    }

    public function getIsaPositions(): array
    {
        $client = $this->isaClient->getPersonalPortfolio();
        $reflection = new \ReflectionClass($client);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $httpClient = $property->getValue($client);

        $response = $httpClient->get('/api/v0/equity/positions', []);
        $data = json_decode($response, true);

        return $this->parsePositions($data);
    }

    public function getSippPositions(): array
    {
        return [];
    }

    public function getAccountSummary(string $accountType): array
    {
        $client = match($accountType) {
            'INVEST' => $this->investClient,
            'ISA' => $this->isaClient,
            'SIPP' => $this->sippClient,
            default => throw new \InvalidArgumentException('Invalid account type'),
        };

        return $client->getAccountData()->getAccount();
    }

    public function getInvestOrders(?string $fromDate = null): array
    {
        return $this->fetchAllOrders($this->investClient, 'INVEST', $fromDate);
    }

    public function getIsaOrders(?string $fromDate = null): array
    {
        return $this->fetchAllOrders($this->isaClient, 'ISA', $fromDate);
    }

    private function fetchAllOrders(Trading212 $client, string $accountType, ?string $fromDate): array
    {
        $portfolio = $client->getPersonalPortfolio();
        $reflection = new \ReflectionClass($portfolio);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $httpClient = $property->getValue($portfolio);

        $allOrders = [];
        $nextPath = '/api/v0/equity/history/orders?limit=50';
        
        $pageCount = 0;
        $maxPages = 50; // Safety limit for full backfills

        while ($nextPath && $pageCount < $maxPages) {
            $response = $httpClient->get($nextPath, []);
            $data = json_decode($response, true);

            if (!isset($data['items']) || !is_array($data['items'])) {
                break;
            }

            // If we have a fromDate, check if this page has older orders.
            // If it does, we only grab the new ones and then STOP fetching more pages.
            $reachedOlderOrders = false;

            foreach ($data['items'] as $item) {
                $filledAt = $item['fill']['filledAt'] ?? $item['order']['createdAt'] ?? null;
                
                if ($filledAt) {
                    $orderDate = substr($filledAt, 0, 10); // "YYYY-MM-DD"
                    
                    if ($fromDate && $orderDate < $fromDate) {
                        $reachedOlderOrders = true;
                        break; // Stop processing this page
                    }
                }
                
                $allOrders[] = $item;
            }

            if ($reachedOlderOrders) {
                break; // We hit the cutoff date, stop fetching further pages
            }

            $nextPath = $data['nextPagePath'] ?? null;
            $pageCount++;
        }

        return $allOrders;
    }

    private function parsePositions(array $data): array
    {
        $positions = [];

        foreach ($data as $item) {
            $instrument = $item['instrument'] ?? [];
            $walletImpact = $item['walletImpact'] ?? [];

            $t212Ticker = $instrument['ticker'] ?? 'UNKNOWN';
            $ticker = self::TICKER_MAPPING[$t212Ticker] ?? $t212Ticker;

            $name = $instrument['name'] ?? $ticker;
            $isin = $instrument['isin'] ?? '';
            $currency = $instrument['currency'] ?? 'USD';
            
            $position = new \stdClass();
            $position->ticker = $ticker;
            $position->name = $name;
            $position->quantity = $item['quantity'] ?? 0;
            $position->currentPriceGBP = $walletImpact['currentPrice'] ?? 0;
            $position->avgPriceGBP = $walletImpact['averagePricePaid'] ?? 0;
            $position->totalSpentGBP = $walletImpact['totalCost'] ?? 0;
            $position->currentValueGBP = $walletImpact['currentValue'] ?? 0;
            $position->profitLossGBP = $walletImpact['unrealizedProfitLoss'] ?? 0;
            
            $positions[] = $position;
        }

        return $positions;
    }
}
