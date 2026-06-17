<?php

namespace App\Api;

use App\Entity\FootballMatch;
use App\Entity\Pool;
use App\Entity\Prediction;
use App\Entity\User;
use App\Pool\PoolCodeGenerator;
use App\Repository\FootballMatchRepository;
use App\Repository\PoolRepository;
use App\Repository\PredictionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Schrijf-operaties van de API: je eigen voorspelling en — als beheerder —
 * uitslagen, wedstrijden bijwerken en poules beheren.
 */
class WriteApi
{
    use AuthorizesApiUser;

    public function __construct(
        private FootballMatchRepository $matches,
        private PredictionRepository $predictions,
        private PoolRepository $pools,
        private PoolCodeGenerator $codeGenerator,
        private ApiNormalizer $normalizer,
        private EntityManagerInterface $em,
    ) {
    }

    public function submitPrediction(?User $user, int $matchId, array $body): array
    {
        $user = $this->requireUser($user);
        $match = $this->findMatch($matchId);

        if (!$match->isActive()) {
            throw new ApiException(ApiError::Conflict, 'Deze wedstrijd is nog niet te voorspellen.');
        }
        if ($match->isLocked()) {
            throw new ApiException(ApiError::Conflict, 'Deze wedstrijd is begonnen; je voorspelling kan niet meer worden aangepast.');
        }

        [$homeScore, $awayScore] = $this->requireScores($body);
        $advancingSide = $this->requireSide($body);

        $prediction = $this->predictions->findOneForUserAndMatch($user, $match) ?? new Prediction();
        $prediction->setUser($user)->setFootballMatch($match)
            ->setHomeScore($homeScore)->setAwayScore($awayScore)->setAdvancingSide($advancingSide)
            ->setUpdatedAt(new \DateTimeImmutable());
        $this->em->persist($prediction);
        $this->em->flush();

        return [
            'match' => $match->getId(),
            'prediction' => ['homeScore' => $homeScore, 'awayScore' => $awayScore, 'advancingSide' => $advancingSide],
            'saved' => true,
        ];
    }

    public function setResult(?User $user, int $matchId, array $body): array
    {
        $this->requireAdmin($user);
        $match = $this->findMatch($matchId);
        if ($match->isFinished()) {
            throw new ApiException(ApiError::Conflict, 'Deze wedstrijd is definitief en staat niet meer open.');
        }

        [$homeScore, $awayScore] = $this->requireScores($body);
        $advancingSide = $this->optionalSide($body);
        $finished = (bool) ($body['finished'] ?? false);
        if ($finished && $advancingSide === null) {
            throw new ApiException(ApiError::Validation, 'Voor een definitieve uitslag is advancingSide ("home"/"away") verplicht.');
        }

        $match->setHomeScore($homeScore)->setAwayScore($awayScore)->setAdvancingSide($advancingSide)->setFinished($finished);
        $this->em->flush();

        return $this->normalizer->match($match);
    }

    public function updateMatch(?User $user, int $matchId, array $body): array
    {
        $this->requireAdmin($user);
        $match = $this->findMatch($matchId);

        if (isset($body['home'])) {
            $match->setHomeTeam((string) $body['home']);
        }
        if (isset($body['away'])) {
            $match->setAwayTeam((string) $body['away']);
        }
        if (array_key_exists('active', $body)) {
            $match->setActive((bool) $body['active']);
        }
        if (isset($body['kickoff'])) {
            try {
                $match->setKickoffAt(new \DateTimeImmutable((string) $body['kickoff']));
            } catch (\Exception) {
                throw new ApiException(ApiError::Validation, 'Ongeldige kickoff (gebruik bijv. 2026-06-28T21:00:00).');
            }
        }

        $this->em->flush();

        return $this->normalizer->match($match);
    }

    public function poolsList(?User $user): array
    {
        $this->requireAdmin($user);

        $pools = array_map(static fn (Pool $p): array => [
            'name' => $p->getName(),
            'code' => $p->getCode(),
            'default' => $p->isDefault(),
            'archived' => $p->isArchived(),
            'members' => count($p->getMembers()),
        ], $this->pools->findAllForAdmin());

        return ['pools' => $pools];
    }

    public function createPool(?User $user, array $body): array
    {
        $this->requireAdmin($user);

        $name = trim((string) ($body['name'] ?? ''));
        if ($name === '') {
            throw new ApiException(ApiError::Validation, 'name is verplicht.');
        }

        $code = isset($body['code']) && $body['code'] !== '' ? strtolower((string) $body['code']) : $this->codeGenerator->generate($name);
        if ($this->pools->findOneByCode($code) !== null) {
            throw new ApiException(ApiError::Conflict, 'Er bestaat al een poule met deze code.');
        }

        $pool = (new Pool())->setName($name)->setCode($code);
        if (!empty($body['default'])) {
            foreach ($this->pools->findBy(['isDefault' => true]) as $other) {
                $other->setDefault(false);
            }
            $pool->setDefault(true);
        }
        $this->em->persist($pool);
        $this->em->flush();

        return ['name' => $pool->getName(), 'code' => $pool->getCode(), 'default' => $pool->isDefault()];
    }

    private function findMatch(int $id): FootballMatch
    {
        $match = $this->matches->find($id);
        if ($match === null) {
            throw new ApiException(ApiError::NotFound, 'Wedstrijd niet gevonden.');
        }

        return $match;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function requireScores(array $body): array
    {
        $home = $this->intOrNull($body['homeScore'] ?? null);
        $away = $this->intOrNull($body['awayScore'] ?? null);
        if ($home === null || $home < 0) {
            throw new ApiException(ApiError::Validation, 'homeScore is verplicht en moet 0 of hoger zijn.');
        }
        if ($away === null || $away < 0) {
            throw new ApiException(ApiError::Validation, 'awayScore is verplicht en moet 0 of hoger zijn.');
        }

        return [$home, $away];
    }

    private function requireSide(array $body): string
    {
        $side = $this->optionalSide($body);
        if ($side === null) {
            throw new ApiException(ApiError::Validation, 'advancingSide is verplicht en moet "home" of "away" zijn.');
        }

        return $side;
    }

    private function optionalSide(array $body): ?string
    {
        $side = $body['advancingSide'] ?? null;
        if ($side === null) {
            return null;
        }
        if (!in_array($side, [FootballMatch::SIDE_HOME, FootballMatch::SIDE_AWAY], true)) {
            throw new ApiException(ApiError::Validation, 'advancingSide moet "home" of "away" zijn.');
        }

        return $side;
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;
    }
}
