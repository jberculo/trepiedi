<?php

namespace App\DataFixtures;

use App\Entity\FootballMatch;
use App\Entity\Prediction;
use App\Entity\Round;
use App\Entity\Team;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    /** @var array<string, Team> */
    private array $teams = [];

    public function __construct(private UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $now = new \DateTimeImmutable('2026-06-12 12:00:00');

        $this->createTeams($manager);
        $players = $this->createUsers($manager);

        $achtste = $this->round($manager, 'Achtste finales', 1);
        $kwart = $this->round($manager, 'Kwartfinales', 2);
        $halve = $this->round($manager, 'Halve finales', 3);
        $finale = $this->round($manager, 'Finale', 4);

        // Achtste finales — gespeeld (8 wedstrijden).
        $achtsteData = [
            ['NED', 'POL', 2, 1, 'NED'],
            ['FRA', 'SUI', 1, 1, 'FRA'],
            ['ESP', 'DEN', 3, 0, 'ESP'],
            ['ENG', 'JPN', 0, 0, 'JPN'],
            ['GER', 'MAR', 2, 0, 'GER'],
            ['POR', 'BRA', 1, 2, 'BRA'],
            ['BEL', 'ARG', 0, 1, 'ARG'],
            ['ITA', 'CRO', 2, 2, 'CRO'],
        ];
        $finishedMatches = [];
        $i = 0;
        foreach ($achtsteData as [$h, $a, $hs, $as, $adv]) {
            $kickoff = $now->modify(sprintf('-%d days', 10 - intdiv($i, 4)));
            $finishedMatches[] = $this->finishedMatch($manager, $achtste, $h, $a, $kickoff, $hs, $as, $adv);
            ++$i;
        }

        // Kwartfinales — gespeeld (4 wedstrijden).
        $kwartData = [
            ['NED', 'FRA', 1, 2, 'FRA'],
            ['ESP', 'JPN', 2, 1, 'ESP'],
            ['GER', 'BRA', 0, 1, 'BRA'],
            ['ARG', 'CRO', 3, 1, 'ARG'],
        ];
        foreach ($kwartData as $j => [$h, $a, $hs, $as, $adv]) {
            $kickoff = $now->modify('-5 days')->modify(sprintf('+%d hours', $j * 3));
            $finishedMatches[] = $this->finishedMatch($manager, $kwart, $h, $a, $kickoff, $hs, $as, $adv);
        }

        // Halve finales — open (toekomst).
        $this->openMatch($manager, $halve, 'FRA', 'ESP', $now->modify('+3 days'));
        $this->openMatch($manager, $halve, 'BRA', 'ARG', $now->modify('+4 days'));

        // Finale — open (toekomst). Teams indicatief; admin past aan na de halve finales.
        $this->openMatch($manager, $finale, 'FRA', 'BRA', $now->modify('+8 days'));

        $this->createPredictions($manager, $players, $finishedMatches, $now);

        $manager->flush();
    }

    private function createTeams(ObjectManager $manager): void
    {
        $teams = [
            'NED' => 'Nederland', 'POL' => 'Polen', 'FRA' => 'Frankrijk', 'SUI' => 'Zwitserland',
            'ESP' => 'Spanje', 'DEN' => 'Denemarken', 'ENG' => 'Engeland', 'JPN' => 'Japan',
            'GER' => 'Duitsland', 'MAR' => 'Marokko', 'POR' => 'Portugal', 'BRA' => 'Brazilië',
            'BEL' => 'België', 'ARG' => 'Argentinië', 'ITA' => 'Italië', 'CRO' => 'Kroatië',
        ];
        foreach ($teams as $code => $name) {
            $team = (new Team())->setName($name)->setCode($code);
            $manager->persist($team);
            $this->teams[$code] = $team;
        }
    }

    /**
     * @return list<User>
     */
    private function createUsers(ObjectManager $manager): array
    {
        $admin = (new User())
            ->setEmail('admin@trepiedi.test')
            ->setDisplayName('Beheerder')
            ->setSlug(\App\Util\Slug::make('Beheerder'))
            ->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->hasher->hashPassword($admin, 'admin'));
        $manager->persist($admin);

        $players = [];
        foreach (['Anne', 'Bram', 'Chris', 'Diana'] as $name) {
            $user = (new User())
                ->setEmail(strtolower($name) . '@trepiedi.test')
                ->setDisplayName($name)
                ->setSlug(\App\Util\Slug::make($name));
            $user->setPassword($this->hasher->hashPassword($user, 'test'));
            $manager->persist($user);
            $players[] = $user;
        }

        return $players;
    }

    private function round(ObjectManager $manager, string $name, int $order): Round
    {
        $round = (new Round())->setName($name)->setSortOrder($order)->setWeight(1.0);
        $manager->persist($round);

        return $round;
    }

    private function finishedMatch(
        ObjectManager $manager,
        Round $round,
        string $home,
        string $away,
        \DateTimeImmutable $kickoff,
        int $homeScore,
        int $awayScore,
        string $advancing,
    ): FootballMatch {
        $match = (new FootballMatch())
            ->setRound($round)
            ->setHomeTeam($this->teams[$home])
            ->setAwayTeam($this->teams[$away])
            ->setKickoffAt($kickoff)
            ->setHomeScore($homeScore)
            ->setAwayScore($awayScore)
            ->setAdvancingTeam($this->teams[$advancing])
            ->setFinished(true);
        $manager->persist($match);

        return $match;
    }

    private function openMatch(
        ObjectManager $manager,
        Round $round,
        string $home,
        string $away,
        \DateTimeImmutable $kickoff,
    ): FootballMatch {
        $match = (new FootballMatch())
            ->setRound($round)
            ->setHomeTeam($this->teams[$home])
            ->setAwayTeam($this->teams[$away])
            ->setKickoffAt($kickoff);
        $manager->persist($match);

        return $match;
    }

    /**
     * Gevarieerde voorspellingen zodat het klassement zinvol verschilt.
     *
     * @param list<User>          $players
     * @param list<FootballMatch> $matches
     */
    private function createPredictions(
        ObjectManager $manager,
        array $players,
        array $matches,
        \DateTimeImmutable $now,
    ): void {
        foreach ($matches as $match) {
            foreach ($players as $index => $player) {
                [$home, $away, $advancing] = $this->predictedOutcome($match, $index);

                $prediction = (new Prediction())
                    ->setUser($player)
                    ->setFootballMatch($match)
                    ->setHomeScore($home)
                    ->setAwayScore($away)
                    ->setAdvancingTeam($advancing)
                    ->setUpdatedAt($now->modify('-12 days'));
                $manager->persist($prediction);
            }
        }
    }

    /**
     * @return array{0: int, 1: int, 2: Team}
     */
    private function predictedOutcome(FootballMatch $match, int $playerIndex): array
    {
        $actualHome = (int) $match->getHomeScore();
        $actualAway = (int) $match->getAwayScore();
        $winner = $match->getAdvancingTeam() ?? $match->getHomeTeam();
        $loser = $winner === $match->getHomeTeam() ? $match->getAwayTeam() : $match->getHomeTeam();

        return match ($playerIndex) {
            // Anne: alles perfect.
            0 => [$actualHome, $actualAway, $winner],
            // Bram: juiste 'voor', mis op 'tegen', juiste winnaar.
            1 => [$actualHome, $actualAway + 1, $winner],
            // Chris: beide scores mis, verkeerde winnaar.
            2 => [$actualHome + 1, $actualAway + 1, $loser],
            // Diana: exacte uitslag; vanaf de kwartfinales ook de winnaar goed (stijgt in het klassement).
            default => [
                $actualHome,
                $actualAway,
                $match->getRound()?->getName() === 'Kwartfinales' ? $winner : $loser,
            ],
        };
    }
}
