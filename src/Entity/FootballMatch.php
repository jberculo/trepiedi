<?php

namespace App\Entity;

use App\Repository\FootballMatchRepository;
use App\Util\MatchOutcome;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Een knock-outwedstrijd (vanaf de 16e finales).
 * De score betreft de stand na reguliere speeltijd en eventuele verlenging (zonder penalty's);
 * advancingSide geeft aan welke kant (thuis/uit) doorgaat (eventueel na strafschoppen).
 * De ploegen zijn vrije tekst; er is geen aparte teamadministratie.
 */
#[ORM\Entity(repositoryClass: FootballMatchRepository::class)]
#[ORM\Table(name: 'football_match')]
class FootballMatch
{
    public const SIDE_HOME = 'home';
    public const SIDE_AWAY = 'away';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Round::class, inversedBy: 'matches')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Round $round = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private ?string $homeTeam = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\NotEqualTo(propertyPath: 'homeTeam', message: 'validation.teams_must_differ')]
    private ?string $awayTeam = null;

    #[ORM\Column]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $kickoffAt = null;

    /**
     * Doelpunten thuisploeg na reguliere speeltijd en verlenging, zonder penalty's
     * (null zolang er geen uitslag is).
     */
    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $homeScore = null;

    /**
     * Doelpunten uitploeg na reguliere speeltijd en verlenging, zonder penalty's.
     */
    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $awayScore = null;

    /**
     * Welke kant gaat door: 'home' of 'away' (na eventuele verlenging/penalty's), null zolang onbekend.
     */
    #[ORM\Column(length: 4, nullable: true)]
    #[Assert\Choice(choices: [self::SIDE_HOME, self::SIDE_AWAY])]
    private ?string $advancingSide = null;

    #[ORM\Column]
    private bool $finished = false;

    /**
     * Inactieve wedstrijden zijn (nog) niet invulbaar voor gebruikers, maar tellen
     * wel mee in de maximale score van het hele toernooi.
     */
    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    /**
     * Of de huidige uitslag via de externe API/MCP is binnengekomen (true) of handmatig
     * via de backend is ingevoerd/aangepast (false). Wordt op true gezet door de
     * API/MCP-uitslag en teruggezet op false zodra de uitslag in de backend naar een
     * afwijkende waarde wordt aangepast.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $resultViaExternalApi = false;

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

    public function getHomeTeam(): ?string
    {
        return $this->homeTeam;
    }

    public function setHomeTeam(?string $homeTeam): static
    {
        $this->homeTeam = $homeTeam;

        return $this;
    }

    public function getAwayTeam(): ?string
    {
        return $this->awayTeam;
    }

    public function setAwayTeam(?string $awayTeam): static
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

    public function getAdvancingSide(): ?string
    {
        return $this->advancingSide;
    }

    public function setAdvancingSide(?string $advancingSide): static
    {
        $this->advancingSide = $advancingSide;

        return $this;
    }

    /**
     * De naam van de ploeg aan een kant ('home'/'away'), of null.
     */
    public function teamForSide(?string $side): ?string
    {
        return match ($side) {
            self::SIDE_HOME => $this->homeTeam,
            self::SIDE_AWAY => $this->awayTeam,
            default => null,
        };
    }

    /**
     * De naam van de doorgaande ploeg (op basis van advancingSide), of null.
     */
    public function getAdvancingTeam(): ?string
    {
        return $this->teamForSide($this->advancingSide);
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

    public function isResultViaExternalApi(): bool
    {
        return $this->resultViaExternalApi;
    }

    public function setResultViaExternalApi(bool $resultViaExternalApi): static
    {
        $this->resultViaExternalApi = $resultViaExternalApi;

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
     * Aantal uur na de aftrap waarna een nog niet afgeronde wedstrijd niet meer
     * als "gestart" maar als "wachtend op uitslag" geldt.
     */
    public const RESULT_GRACE_HOURS = 2;

    /**
     * Voorspellingen kunnen niet meer worden aangepast vanaf de aftrap.
     */
    public function isLocked(?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable();

        return $this->kickoffAt !== null && $now >= $this->kickoffAt;
    }

    /**
     * De wedstrijd is begonnen, de reguliere speeltijd is ruimschoots voorbij,
     * maar er is nog geen definitieve uitslag ingevoerd.
     */
    public function isAwaitingResult(?\DateTimeImmutable $now = null): bool
    {
        if ($this->kickoffAt === null || $this->finished) {
            return false;
        }

        $now ??= new \DateTimeImmutable();

        return $now >= $this->kickoffAt->modify(sprintf('+%d hours', self::RESULT_GRACE_HOURS));
    }

    public function hasResult(): bool
    {
        return $this->finished && $this->homeScore !== null && $this->awayScore !== null;
    }

    /**
     * Een tegenstrijdige uitslag: de score-winnaar is niet de doorgaande ploeg.
     */
    public function hasInconsistentResult(): bool
    {
        return MatchOutcome::isInconsistent($this->homeScore, $this->awayScore, $this->advancingSide);
    }

    public function __toString(): string
    {
        return sprintf('%s - %s', $this->homeTeam, $this->awayTeam);
    }
}
