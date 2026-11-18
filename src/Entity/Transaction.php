<?php

namespace App\Entity;

use App\Repository\TransactionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Table(name: 'transactions')]
#[ORM\Index(name: 'idx_ticker', columns: ['ticker'])]
#[ORM\Index(name: 'idx_type', columns: ['type'])]
#[ORM\Index(name: 'idx_trade_date', columns: ['trade_date'])]
#[ORM\Index(name: 'idx_account', columns: ['account_type'])]
#[ORM\Index(name: 'idx_fill_id', columns: ['fill_id'])]
#[ORM\Index(name: 'idx_order_id', columns: ['order_id'])]
class Transaction
{
    const TYPE_BUY = 'BUY';
    const TYPE_SELL = 'SELL';

    const SOURCE_API = 'API';
    const SOURCE_CRUD = 'CRUD';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Position::class)]
    #[ORM\JoinColumn(name: 'position_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Position $position = null;

    #[ORM\Column(length: 20)]
    private ?string $ticker = null;

    #[ORM\Column(name: 'account_type', length: 50)]
    private ?string $accountType = null;

    #[ORM\Column(name: 'type', length: 10)]
    private ?string $type = null;

    #[ORM\Column(type: 'float')]
    private ?float $shares = null;

    #[ORM\Column(name: 'price_per_share', type: 'float')]
    private ?float $pricePerShare = null;

    #[ORM\Column(name: 'total_value', type: 'float')]
    private ?float $totalValue = null;

    #[ORM\Column(name: 'avg_price_at_time', type: 'float', nullable: true)]
    private ?float $avgPriceAtTime = null;

    #[ORM\Column(name: 'realised_pl', type: 'float', nullable: true)]
    private ?float $realisedPl = null;

    #[ORM\Column(name: 'realised_pl_percent', type: 'float', nullable: true)]
    private ?float $realisedPlPercent = null;

    #[ORM\Column(name: 'trade_date', type: 'datetime')]
    private ?\DateTimeInterface $tradeDate = null;

    #[ORM\Column(length: 20, options: ['default' => 'API'])]
    private string $source = self::SOURCE_API;

    #[ORM\Column(name: 'fill_id', length: 50, nullable: true)]
    private ?string $fillId = null;

    #[ORM\Column(name: 'order_id', length: 50, nullable: true)]
    private ?string $orderId = null;

    #[ORM\Column(name: 'fx_rate', type: 'float', nullable: true)]
    private ?float $fxRate = null;

    #[ORM\Column(name: 'net_value_gbp', type: 'float', nullable: true)]
    private ?float $netValueGbp = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPosition(): ?Position
    {
        return $this->position;
    }

    public function setPosition(?Position $position): static
    {
        $this->position = $position;
        return $this;
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

    public function getAccountType(): ?string
    {
        return $this->accountType;
    }

    public function setAccountType(string $accountType): static
    {
        $this->accountType = $accountType;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
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

    public function getPricePerShare(): ?float
    {
        return $this->pricePerShare;
    }

    public function setPricePerShare(float $pricePerShare): static
    {
        $this->pricePerShare = $pricePerShare;
        return $this;
    }

    public function getTotalValue(): ?float
    {
        return $this->totalValue;
    }

    public function setTotalValue(float $totalValue): static
    {
        $this->totalValue = $totalValue;
        return $this;
    }

    public function getAvgPriceAtTime(): ?float
    {
        return $this->avgPriceAtTime;
    }

    public function setAvgPriceAtTime(?float $avgPriceAtTime): static
    {
        $this->avgPriceAtTime = $avgPriceAtTime;
        return $this;
    }

    public function getRealisedPl(): ?float
    {
        return $this->realisedPl;
    }

    public function setRealisedPl(?float $realisedPl): static
    {
        $this->realisedPl = $realisedPl;
        return $this;
    }

    public function getRealisedPlPercent(): ?float
    {
        return $this->realisedPlPercent;
    }

    public function setRealisedPlPercent(?float $realisedPlPercent): static
    {
        $this->realisedPlPercent = $realisedPlPercent;
        return $this;
    }

    public function getTradeDate(): ?\DateTimeInterface
    {
        return $this->tradeDate;
    }

    public function setTradeDate(\DateTimeInterface $tradeDate): static
    {
        $this->tradeDate = $tradeDate;
        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;
        return $this;
    }

    public function getFillId(): ?string
    {
        return $this->fillId;
    }

    public function setFillId(?string $fillId): static
    {
        $this->fillId = $fillId;
        return $this;
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(?string $orderId): static
    {
        $this->orderId = $orderId;
        return $this;
    }

    public function getFxRate(): ?float
    {
        return $this->fxRate;
    }

    public function setFxRate(?float $fxRate): static
    {
        $this->fxRate = $fxRate;
        return $this;
    }

    public function getNetValueGbp(): ?float
    {
        return $this->netValueGbp;
    }

    public function setNetValueGbp(?float $netValueGbp): static
    {
        $this->netValueGbp = $netValueGbp;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function isSale(): bool
    {
        return $this->type === self::TYPE_SELL;
    }

    public function isBuy(): bool
    {
        return $this->type === self::TYPE_BUY;
    }
}
