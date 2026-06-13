<?php

namespace App\Entity;

use App\Repository\FootballMatchRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Een knock-outwedstrijd (vanaf de 16e finales).
 * De score betreft de stand na reguliere speeltijd én eventuele verlenging (zonder penalty's);
 * advancingTeam is de winnaar die doorgaat (eventueel na strafschoppen).
 */
#[ORM\Entity(repositoryClass: FootballMatchRepository::class)]
#[ORM\Table(name: 'football_match')]
class FootballMatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Round::class, inversedBy: 'matches')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Round $round = null;

    #[ORM\ManyToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Team $homeTeam = null;

    #[ORM\ManyToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Assert\NotEqualTo(propertyPath: 'homeTeam', message: 'validation.teams_must_differ')]
    private ?Team $awayTeam = null;

    #[ORM\Column]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $kickoffAt = null;

    /**
     * Doelpunten thuisploeg na reguliere speeltijd én verlenging, zonder penalty's
     * (null zolang er geen uitslag is).
     */
    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $homeScore = null;

    /**
     * Doelpunten uitploeg na reguliere speeltijd én verlenging, zonder penalty's.
     */
    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $awayScore = null;

    /**
     * De winnaar die doorgaat (na eventuele verlenging/penalty's).
     */
    #[ORM\ManyToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Team $advancingTeam = null;

    #[ORM\Column]
    private bool $finished = false;

    /**
     * Inactieve wedstrijden zijn (nog) niet invulbaar voor gebruikers, maar tellen
     * wel mee in de maximale score van het hele toernooi.
     */
    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    /**
     * @var Collection<int, Prediction>
     */
    #[ORM\OneToMany(targetEntity: Prediction::class, mappedBy: 'footballMatch', cascade: ['remove'])]
    private Collection $predictions;

    public function __construct()
    {
        $this->predictions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRound(): ?Round
    {
        return $this->round;
    }

    public function setRound(?Round $round): static
    {
        $this->round = $round;

        return $this;
    }

    public function getHomeTeam(): ?Team
    {
        return $this->homeTeam;
    }

    public function setHomeTeam(?Team $homeTeam): static
    {
        $this->homeTeam = $homeTeam;

        return $this;
    }

    public function getAwayTeam(): ?Team
    {
        return $this->awayTeam;
    }

    public function setAwayTeam(?Team $awayTeam): static
    {
        $this->awayTeam = $awayTeam;

        return $this;
    }

    public function getKickoffAt(): ?\DateTimeImmutable
    {
        return $this->kickoffAt;
    }

    public function setKickoffAt(?\DateTimeImmutable $kickoffAt): static
    {
        $this->kickoffAt = $kickoffAt;

        return $this;
    }

    public function getHomeScore(): ?int
    {
        return $this->homeScore;
    }

    public function setHomeScore(?int $homeScore): static
    {
        $this->homeScore = $homeScore;

        return $this;
    }

    public function getAwayScore(): ?int
    {
        return $this->awayScore;
    }

    public function setAwayScore(?int $awayScore): static
    {
        $this->awayScore = $awayScore;

        return $this;
    }

    public function getAdvancingTeam(): ?Team
    {
        return $this->advancingTeam;
    }

    public function setAdvancingTeam(?Team $advancingTeam): static
    {
        $this->advancingTeam = $advancingTeam;

        return $this;
    }

    public function isFinished(): bool
    {
        return $this->finished;
    }

    public function setFinished(bool $finished): static
    {
        $this->finished = $finished;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    /**
     * @return Collection<int, Prediction>
     */
    public function getPredictions(): Collection
    {
        return $this->predictions;
    }

    /**
     * Voorspellingen kunnen niet meer worden aangepast vanaf de aftrap.
     */
    public function isLocked(?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable();

        return $this->kickoffAt !== null && $now >= $this->kickoffAt;
    }

    public function hasResult(): bool
    {
        return $this->finished && $this->homeScore !== null && $this->awayScore !== null;
    }

    public function __toString(): string
    {
        return sprintf('%s - %s', $this->homeTeam, $this->awayTeam);
    }
}
