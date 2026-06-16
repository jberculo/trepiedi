<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
     * Persoonlijke API-sleutel voor de stand-/uitslagen-API. Null tot hij voor het
     * eerst wordt gegenereerd.
     */
    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $apiToken = null;

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

    public function getApiToken(): ?string
    {
        return $this->apiToken;
    }

    public function setApiToken(?string $apiToken): static
    {
        $this->apiToken = $apiToken;

        return $this;
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
