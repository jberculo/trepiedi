<?php

namespace App\Entity;

use App\Repository\RoundRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RoundRepository::class)]
class Round
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private string $name = '';

    /**
     * Bepaalt de volgorde van de ronden (16e finales eerst, finale laatst).
     */
    #[ORM\Column]
    private int $sortOrder = 0;

    /**
     * Relatief gewicht van de ronde in het eindklassement.
     * Elke ronde telt standaard even zwaar (1.0); de behaalde punten worden
     * per ronde genormaliseerd naar dit gewicht.
     */
    #[ORM\Column]
    #[Assert\Positive]
    private float $weight = 1.0;

    /**
     * @var Collection<int, FootballMatch>
     */
    #[ORM\OneToMany(targetEntity: FootballMatch::class, mappedBy: 'round', cascade: ['remove'])]
    #[ORM\OrderBy(['kickoffAt' => 'ASC'])]
    private Collection $matches;

    public function __construct()
    {
        $this->matches = new ArrayCollection();
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

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function getWeight(): float
    {
        return $this->weight;
    }

    public function setWeight(float $weight): static
    {
        $this->weight = $weight;

        return $this;
    }

    /**
     * @return Collection<int, FootballMatch>
     */
    public function getMatches(): Collection
    {
        return $this->matches;
    }

    public function addMatch(FootballMatch $match): static
    {
        if (!$this->matches->contains($match)) {
            $this->matches->add($match);
            $match->setRound($this);
        }

        return $this;
    }

    public function removeMatch(FootballMatch $match): static
    {
        if ($this->matches->removeElement($match)) {
            if ($match->getRound() === $this) {
                $match->setRound(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
