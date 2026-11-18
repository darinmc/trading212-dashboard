<?php

namespace App\Repository;

use App\Entity\Transaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    public function findAllSales(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.type = :type')
            ->setParameter('type', Transaction::TYPE_SELL)
            ->orderBy('t.tradeDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAllBuys(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.type = :type')
            ->setParameter('type', Transaction::TYPE_BUY)
            ->orderBy('t.tradeDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByTicker(string $ticker): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.ticker = :ticker')
            ->setParameter('ticker', $ticker)
            ->orderBy('t.tradeDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Filtered, paginated transactions.
     *
     * @return array{items: Transaction[], total: int}
     */
    public function findFiltered(
        ?string $account = null,
        ?string $type = null,
        ?string $ticker = null,
        ?string $taxYear = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        string $sort = 'date',
        string $dir = 'DESC',
        int $page = 1,
        int $perPage = 50
    ): array {
        $qb = $this->createQueryBuilder('t');

        // Ignore zero-value corporate actions (like fractional share settlements)
        $qb->andWhere('t.totalValue > 0.01');

        if ($account) {
            $qb->andWhere('t.accountType = :account')
               ->setParameter('account', $account);
        }

        if ($type) {
            $qb->andWhere('t.type = :type')
               ->setParameter('type', $type);
        }

        if ($ticker) {
            $qb->andWhere('t.ticker LIKE :ticker')
               ->setParameter('ticker', '%' . $ticker . '%');
        }

        // Date range filters (explicit)
        if ($dateFrom) {
            $qb->andWhere('t.tradeDate >= :dateFrom')
               ->setParameter('dateFrom', new \DateTime($dateFrom . ' 00:00:00'));
        }

        if ($dateTo) {
            $qb->andWhere('t.tradeDate <= :dateTo')
               ->setParameter('dateTo', new \DateTime($dateTo . ' 23:59:59'));
        }

        // Tax year filter (only applies if no explicit date range is set)
        if ($taxYear && !$dateFrom && !$dateTo) {
            [$start, $end] = $this->getTaxYearRange($taxYear);
            if ($start && $end) {
                $qb->andWhere('t.tradeDate >= :start')
                   ->andWhere('t.tradeDate < :end')
                   ->setParameter('start', $start)
                   ->setParameter('end', $end);
            }
        }

        // Count total before pagination
        $countQb = clone $qb;
        $countQb->select('COUNT(t.id)');
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        // Sort field mapping
        $sortField = match ($sort) {
            'ticker' => 't.ticker',
            'shares' => 't.shares',
            'price' => 't.pricePerShare',
            'value' => 't.totalValue',
            'pl' => 't.realisedPl',
            'account' => 't.accountType',
            'type' => 't.type',
            default => 't.tradeDate',
        };

        $direction = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
        $qb->orderBy($sortField, $direction);

        // Pagination
        $offset = max(0, ($page - 1) * $perPage);
        $qb->setMaxResults($perPage)
           ->setFirstResult($offset);

        $items = $qb->getQuery()->getResult();

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    public function getTaxYearSummary(string $taxYear): array
    {
        [$start, $end] = $this->getTaxYearRange($taxYear);

        if (!$start || !$end) {
            return $this->emptyTaxSummary();
        }

        $result = $this->createQueryBuilder('t')
            ->select([
                'COUNT(t.id) as disposals',
                'SUM(t.totalValue) as proceeds',
                'SUM(t.realisedPl) as realisedPl',
            ])
            ->andWhere('t.accountType = :account')
            ->andWhere('t.type = :type')
            ->andWhere('t.tradeDate >= :start')
            ->andWhere('t.tradeDate < :end')
            ->setParameter('account', 'INVEST')
            ->setParameter('type', Transaction::TYPE_SELL)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleResult();

        $disposals = (int) ($result['disposals'] ?? 0);
        $proceeds = (float) ($result['proceeds'] ?? 0);
        $realisedPl = (float) ($result['realisedPl'] ?? 0);

        return [
            'taxYear' => $taxYear,
            'disposals' => $disposals,
            'proceeds' => $proceeds,
            'realisedPl' => $realisedPl,
            'grossGains' => max(0, $realisedPl),
            'grossLosses' => min(0, $realisedPl),
            'allowance' => 3000.0,
            'taxableGain' => max(0, $realisedPl - 3000.0),
        ];
    }

    public function getAvailableTaxYears(): array
    {
        $dates = $this->createQueryBuilder('t')
            ->select('MIN(t.tradeDate) as earliest, MAX(t.tradeDate) as latest')
            ->getQuery()
            ->getSingleResult();

        if (!$dates['earliest'] || !$dates['latest']) {
            return [];
        }

        $earliest = $dates['earliest'] instanceof \DateTimeInterface
            ? $dates['earliest']
            : new \DateTime($dates['earliest']);

        $latest = $dates['latest'] instanceof \DateTimeInterface
            ? $dates['latest']
            : new \DateTime($dates['latest']);

        $start = (int) $earliest->format('Y');
        $end = (int) $latest->format('Y');

        $years = [];
        for ($y = $start; $y <= $end + 1; $y++) {
            $years[] = $y . '/' . substr($y + 1, -2);
        }

        return array_reverse($years);
    }

    /**
     * Calculate the current UK tax year string (e.g. "2026/27").
     * A tax year starts on 6 April.
     */
    public function getCurrentTaxYear(): string
    {
        $now = new \DateTime();
        $year = (int) $now->format('Y');
        $month = (int) $now->format('n');
        $day = (int) $now->format('j');

        // If we're before 6 April, we're still in the previous tax year
        if ($month < 4 || ($month === 4 && $day < 6)) {
            $year--;
        }

        return $year . '/' . substr($year + 1, -2);
    }

    public function getTotalRealisedPL(int $year): float
    {
        $start = new \DateTime("$year-01-01 00:00:00");
        $end = new \DateTime("$year-12-31 23:59:59");

        $result = $this->createQueryBuilder('t')
            ->select('SUM(t.realisedPl) as total')
            ->andWhere('t.type = :type')
            ->andWhere('t.tradeDate >= :start')
            ->andWhere('t.tradeDate <= :end')
            ->setParameter('type', Transaction::TYPE_SELL)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    public function findSalesByAccount(string $accountType): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.type = :type')
            ->andWhere('t.accountType = :account')
            ->setParameter('type', Transaction::TYPE_SELL)
            ->setParameter('account', $accountType)
            ->orderBy('t.tradeDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    private function getTaxYearRange(string $taxYear): array
    {
        if (!preg_match('/^(\d{4})\/(\d{2})$/', $taxYear, $matches)) {
            return [null, null];
        }

        $startYear = (int) $matches[1];
        $endYear = $startYear + 1;

        $start = new \DateTime("$startYear-04-06 00:00:00");
        $end = new \DateTime("$endYear-04-06 00:00:00");

        return [$start, $end];
    }

    private function emptyTaxSummary(): array
    {
        return [
            'taxYear' => null,
            'disposals' => 0,
            'proceeds' => 0,
            'realisedPl' => 0,
            'grossGains' => 0,
            'grossLosses' => 0,
            'allowance' => 3000.0,
            'taxableGain' => 0,
        ];
    }
}
