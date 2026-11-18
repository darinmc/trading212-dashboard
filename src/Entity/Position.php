<?php

namespace App\Entity;

use App\Repository\PositionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PositionRepository::class)]
class Position
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $ticker = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: 'float')]
    private ?float $shares = null;

    #[ORM\Column]
    private ?float $currentPrice = null;

    #[ORM\Column]
    private ?float $avgPrice = null;

    #[ORM\Column]
    private ?float $totalSpent = null;

    #[ORM\Column]
    private ?float $currentValue = null;

    #[ORM\Column]
    private ?float $profitLoss = null;

    #[ORM\Column]
    private ?float $profitLossPercent = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $lastUpdated = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $important = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $checkFlag = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $financials = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $soldDate = null;

    #[ORM\ManyToOne(inversedBy: 'positions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Portfolio $portfolio = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTicker(): ?string
    {
        return $this->ticker;
    }

    public function setTicker(string $ticker): static
    {
        $this->ticker = $ticker;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getShares(): ?float
    {
        return $this->shares;
    }

    public function setShares(float $shares): static
    {
        $this->shares = $shares;
        return $this;
    }

    public function getCurrentPrice(): ?float
    {
        return $this->currentPrice;
    }

    public function setCurrentPrice(float $currentPrice): static
    {
        $this->currentPrice = $currentPrice;
        return $this;
    }

    public function getAvgPrice(): ?float
    {
        return $this->avgPrice;
    }

    public function setAvgPrice(float $avgPrice): static
    {
        $this->avgPrice = $avgPrice;
        return $this;
    }

    public function getTotalSpent(): ?float
    {
        return $this->totalSpent;
    }

    public function setTotalSpent(float $totalSpent): static
    {
        $this->totalSpent = $totalSpent;
        return $this;
    }

    public function getCurrentValue(): ?float
    {
        return $this->currentValue;
    }

    public function setCurrentValue(float $currentValue): static
    {
        $this->currentValue = $currentValue;
        return $this;
    }

    public function getProfitLoss(): ?float
    {
        return $this->profitLoss;
    }

    public function setProfitLoss(float $profitLoss): static
    {
        $this->profitLoss = $profitLoss;
        return $this;
    }

    public function getProfitLossPercent(): ?float
    {
        return $this->profitLossPercent;
    }

    public function setProfitLossPercent(float $profitLossPercent): static
    {
        $this->profitLossPercent = $profitLossPercent;
        return $this;
    }

    public function getLastUpdated(): ?\DateTime
    {
        return $this->lastUpdated;
    }

    public function setLastUpdated(?\DateTime $lastUpdated): static
    {
        $this->lastUpdated = $lastUpdated;
        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;
        return $this;
    }

    public function getImportant(): ?bool
    {
        return $this->important;
    }

    public function setImportant(?bool $important): static
    {
        $this->important = $important;
        return $this;
    }

    public function getCheckFlag(): ?bool
    {
        return $this->checkFlag;
    }

    public function setCheckFlag(?bool $checkFlag): static
    {
        $this->checkFlag = $checkFlag;
        return $this;
    }

    public function getFinancials(): ?bool
    {
        return $this->financials;
    }

    public function setFinancials(?bool $financials): static
    {
        $this->financials = $financials;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getSoldDate(): ?\DateTime
    {
        return $this->soldDate;
    }

    public function setSoldDate(?\DateTime $soldDate): static
    {
        $this->soldDate = $soldDate;
        return $this;
    }

    public function getPortfolio(): ?Portfolio
    {
        return $this->portfolio;
    }

    public function setPortfolio(?Portfolio $portfolio): static
    {
        $this->portfolio = $portfolio;
        return $this;
    }
}
