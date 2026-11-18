<?php

namespace App\Repository;

use App\Entity\Position;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Position>
 */
class PositionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Position::class);
    }

    /**
     * Find all active (not sold) positions, ordered by ticker.
     *
     * @return Position[]
     */
    public function findActivePositions(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.ticker', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all sold (inactive) positions, ordered by sold date descending.
     *
     * @return Position[]
     */
    public function findSoldPositions(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', false)
            ->orderBy('p.soldDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active positions for a specific portfolio.
     *
     * @return Position[]
     */
    public function findActiveByPortfolio($portfolio): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.portfolio = :portfolio')
            ->setParameter('active', true)
            ->setParameter('portfolio', $portfolio)
            ->orderBy('p.ticker', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
