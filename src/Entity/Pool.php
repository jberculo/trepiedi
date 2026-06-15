<?php

namespace App\Entity;

use App\Repository\PoolRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Een poule: een eigen klassement/deelnemerslijst over hetzelfde toernooi.
 * Spelers voorspellen één keer (gedeeld), maar worden binnen elke poule waarvan
 * ze lid zijn apart gerangschikt. Wie geen code gebruikt, komt in de standaardpoule.
 */
#[ORM\Entity(repositoryClass: PoolRepository::class)]
#[ORM\Table(name: 'pool')]
#[UniqueEntity(fields: ['code'], message: 'validation.pool_code_taken')]
class Pool
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private string $name = '';

    /**
     * Inschrijfcode: zit in de uitnodigings-URL (/poule/inschrijven/{code}).
     */
    #[ORM\Column(length: 32, unique: true)]
    #[Assert\NotBlank]
    private string $code = '';

    /**
     * De standaardpoule waarin nieuwe spelers zonder code terechtkomen.
     * Er hoort er hooguit één true te zijn.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $isDefault = false;

    /**
     * Soft-delete: gezet wanneer de poule is gearchiveerd. Gearchiveerde poules
     * verdwijnen uit inschrijven/wisselen/klassement, maar blijven bewaard.
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $archivedAt = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'pools')]
    private Collection $members;

    public function __construct()
    {
        $this->members = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;

        return $this;
    }

    public function getArchivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    public function archive(): static
    {
        $this->archivedAt ??= new \DateTimeImmutable();

        return $this;
    }

    public function restore(): static
    {
        $this->archivedAt = null;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
