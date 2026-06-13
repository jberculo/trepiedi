<?php

namespace App\Entity;

use App\Repository\PredictionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PredictionRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_user_match', columns: ['user_id', 'football_match_id'])]
class Prediction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: FootballMatch::class, inversedBy: 'predictions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?FootballMatch $footballMatch = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'validation.enter_goals')]
    #[Assert\PositiveOrZero]
    #[Assert\LessThanOrEqual(99)]
    private ?int $homeScore = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'validation.enter_goals')]
    #[Assert\PositiveOrZero]
    #[Assert\LessThanOrEqual(99)]
    private ?int $awayScore = null;

    /**
     * Welk team gaat volgens de voorspeller door.
     */
    #[ORM\ManyToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Team $advancingTeam = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getFootballMatch(): ?FootballMatch
    {
        return $this->footballMatch;
    }

    public function setFootballMatch(?FootballMatch $footballMatch): static
    {
        $this->footballMatch = $footballMatch;

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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
