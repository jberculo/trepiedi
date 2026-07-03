<?php

namespace App\Entity;

use App\Notice\NoticeType;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['email'], message: 'validation.email_taken')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email = '';

    /**
     * @var list<string>
     */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    private string $displayName = '';

    /**
     * URL-identifier op basis van de weergavenaam (bijv. "anne").
     */
    #[ORM\Column(length: 130, unique: true, nullable: true)]
    private ?string $slug = null;

    /**
     * Bestandsnaam van de profielfoto in public/uploads/avatars (null = geen foto).
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    /**
     * Voorkeurstaal van de gebruiker (bepaalt de taal van de site na inloggen).
     */
    #[ORM\Column(length: 5, options: ['default' => 'nl'])]
    #[Assert\Choice(choices: ['nl', 'en'])]
    private string $locale = 'nl';

    /**
     * Poules waarvan de speler lid is. Elke poule is een eigen klassement over
     * dezelfde (gedeelde) voorspellingen.
     *
     * @var Collection<int, Pool>
     */
    #[ORM\ManyToMany(targetEntity: Pool::class, inversedBy: 'members')]
    #[ORM\JoinTable(name: 'user_pool')]
    private Collection $pools;

    /**
     * De poule die de speler nu bekijkt (welk klassement getoond wordt). Null =
     * laat PoolContext een keuze maken (standaardpoule of eerste lidmaatschap).
     */
    #[ORM\ManyToOne(targetEntity: Pool::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Pool $activePool = null;

    /**
     * Publiek lookup-deel van de API-sleutel. De volledige sleutel zelf wordt niet
     * opgeslagen; alleen dit id en een hash van de volledige token.
     */
    #[ORM\Column(length: 16, unique: true, nullable: true)]
    private ?string $apiTokenId = null;

    /**
     * SHA-256-hash van de volledige API-sleutel.
     */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $apiTokenHash = null;

    /**
     * Vrije beheer-melding die na inloggen bovenaan wordt getoond (null/leeg = geen melding).
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notice = null;

    /**
     * Type (en daarmee kleur) van de melding.
     */
    #[ORM\Column(length: 10, enumType: NoticeType::class, options: ['default' => 'info'])]
    private NoticeType $noticeType = NoticeType::Info;

    public function __construct()
    {
        $this->pools = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, Pool>
     */
    public function getPools(): Collection
    {
        return $this->pools;
    }

    public function isInPool(Pool $pool): bool
    {
        return $this->pools->contains($pool);
    }

    public function addPool(Pool $pool): static
    {
        if (!$this->pools->contains($pool)) {
            $this->pools->add($pool);
        }

        return $this;
    }

    public function removePool(Pool $pool): static
    {
        $this->pools->removeElement($pool);

        return $this;
    }

    public function getActivePool(): ?Pool
    {
        return $this->activePool;
    }

    public function setActivePool(?Pool $activePool): static
    {
        $this->activePool = $activePool;

        return $this;
    }

    public function getApiTokenId(): ?string
    {
        return $this->apiTokenId;
    }

    public function setApiTokenId(?string $apiTokenId): static
    {
        $this->apiTokenId = $apiTokenId;

        return $this;
    }

    public function getApiTokenHash(): ?string
    {
        return $this->apiTokenHash;
    }

    public function setApiTokenHash(?string $apiTokenHash): static
    {
        $this->apiTokenHash = $apiTokenHash;

        return $this;
    }

    public function hasApiToken(): bool
    {
        return $this->apiTokenId !== null && $this->apiTokenHash !== null;
    }

    public function getNotice(): ?string
    {
        return $this->notice;
    }

    public function setNotice(?string $notice): static
    {
        $notice = $notice !== null ? trim($notice) : null;
        $this->notice = $notice === '' ? null : $notice;

        return $this;
    }

    public function hasNotice(): bool
    {
        return $this->notice !== null;
    }

    public function getNoticeType(): NoticeType
    {
        return $this->noticeType;
    }

    public function setNoticeType(NoticeType $noticeType): static
    {
        $this->noticeType = $noticeType;

        return $this;
    }

    /**
     * Korte, stabiele vingerafdruk van de meldinginhoud. Samen met de datum vormt
     * dit de wegklik-sleutel: de melding komt elke dag terug en opnieuw zodra de
     * beheerder de tekst of het type wijzigt.
     */
    public function getNoticeSignature(): string
    {
        return substr(hash('sha256', $this->noticeType->value . '|' . (string) $this->notice), 0, 10);
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function isAdmin(): bool
    {
        return in_array('ROLE_ADMIN', $this->getRoles(), true);
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): static
    {
        $this->displayName = $displayName;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // Niets gevoeligs tijdelijk opgeslagen.
    }
}
