<?php

namespace App\DataFixtures;

use App\Entity\FootballMatch;
use App\Entity\Pool;
use App\Entity\Prediction;
use App\Entity\Round;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $now = new \DateTimeImmutable('2026-06-12 12:00:00');

        $pools = $this->createPools($manager);
        $players = $this->createUsers($manager, $pools);

        $achtste = $this->round($manager, 'Achtste finales', 1);
        $kwart = $this->round($manager, 'Kwartfinales', 2);
        $halve = $this->round($manager, 'Halve finales', 3);
        $finale = $this->round($manager, 'Finale', 4);

        // Achtste finales — gespeeld (8 wedstrijden). [thuis, uit, thuisdoelpunten, uitdoelpunten, doorgaande kant]
        $achtsteData = [
            ['Nederland', 'Polen', 2, 1, FootballMatch::SIDE_HOME],
            ['Frankrijk', 'Zwitserland', 1, 1, FootballMatch::SIDE_HOME],
            ['Spanje', 'Denemarken', 3, 0, FootballMatch::SIDE_HOME],
            ['Engeland', 'Japan', 0, 0, FootballMatch::SIDE_AWAY],
            ['Duitsland', 'Marokko', 2, 0, FootballMatch::SIDE_HOME],
            ['Portugal', 'Brazilië', 1, 2, FootballMatch::SIDE_AWAY],
            ['België', 'Argentinië', 0, 1, FootballMatch::SIDE_AWAY],
            ['Italië', 'Kroatië', 2, 2, FootballMatch::SIDE_AWAY],
        ];
        $finishedMatches = [];
        $i = 0;
        foreach ($achtsteData as [$h, $a, $hs, $as, $side]) {
            $kickoff = $now->modify(sprintf('-%d days', 10 - intdiv($i, 4)));
            $finishedMatches[] = $this->finishedMatch($manager, $achtste, $h, $a, $kickoff, $hs, $as, $side);
            ++$i;
        }

        // Kwartfinales — gespeeld (4 wedstrijden).
        $kwartData = [
            ['Nederland', 'Frankrijk', 1, 2, FootballMatch::SIDE_AWAY],
            ['Spanje', 'Japan', 2, 1, FootballMatch::SIDE_HOME],
            ['Duitsland', 'Brazilië', 0, 1, FootballMatch::SIDE_AWAY],
            ['Argentinië', 'Kroatië', 3, 1, FootballMatch::SIDE_HOME],
        ];
        foreach ($kwartData as $j => [$h, $a, $hs, $as, $side]) {
            $kickoff = $now->modify('-5 days')->modify(sprintf('+%d hours', $j * 3));
            $finishedMatches[] = $this->finishedMatch($manager, $kwart, $h, $a, $kickoff, $hs, $as, $side);
        }

        // Halve finales — open (toekomst).
        $this->openMatch($manager, $halve, 'Frankrijk', 'Spanje', $now->modify('+3 days'));
        $this->openMatch($manager, $halve, 'Brazilië', 'Argentinië', $now->modify('+4 days'));

        // Finale — open (toekomst).
        $this->openMatch($manager, $finale, 'Frankrijk', 'Brazilië', $now->modify('+8 days'));

        $this->createPredictions($manager, $players, $finishedMatches, $now);

        $manager->flush();
    }

    /**
     * Standaardpoule "Algemeen" (iedereen) + een tweede poule "Kantoor" met een
     * deel van de spelers, zodat het scopen van het klassement te testen is.
     *
     * @return array{default: Pool, kantoor: Pool}
     */
    private function createPools(ObjectManager $manager): array
    {
        $default = (new Pool())->setName('Tremani')->setCode('algemeen')->setDefault(true);
        $kantoor = (new Pool())->setName('Kantoor')->setCode('kantoor');
        $manager->persist($default);
        $manager->persist($kantoor);

        return ['default' => $default, 'kantoor' => $kantoor];
    }

    /**
     * @param array{default: Pool, kantoor: Pool} $pools
     *
     * @return list<User>
     */
    private function createUsers(ObjectManager $manager, array $pools): array
    {
        $admin = (new User())
            ->setEmail('admin@trepiedi.test')
            ->setDisplayName('Beheerder')
            ->setSlug(\App\Util\Slug::make('Beheerder'))
            ->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->hasher->hashPassword($admin, 'admin'));
        $admin->addPool($pools['default']);
        $manager->persist($admin);

        $players = [];
        foreach (['Anne', 'Bram', 'Chris', 'Diana'] as $index => $name) {
            $user = (new User())
                ->setEmail(strtolower($name) . '@trepiedi.test')
                ->setDisplayName($name)
                ->setSlug(\App\Util\Slug::make($name));
            $user->setPassword($this->hasher->hashPassword($user, 'test'));
            $user->addPool($pools['default']);
            // Anne en Chris zitten óók in de poule "Kantoor".
            if (in_array($index, [0, 2], true)) {
                $user->addPool($pools['kantoor']);
            }
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
        string $advancingSide,
    ): FootballMatch {
        $match = (new FootballMatch())
            ->setRound($round)
            ->setHomeTeam($home)
            ->setAwayTeam($away)
            ->setKickoffAt($kickoff)
            ->setHomeScore($homeScore)
            ->setAwayScore($awayScore)
            ->setAdvancingSide($advancingSide)
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
            ->setHomeTeam($home)
            ->setAwayTeam($away)
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
                [$home, $away, $side] = $this->predictedOutcome($match, $index);

                $prediction = (new Prediction())
                    ->setUser($player)
                    ->setFootballMatch($match)
                    ->setHomeScore($home)
                    ->setAwayScore($away)
                    ->setAdvancingSide($side)
                    ->setUpdatedAt($now->modify('-12 days'));
                $manager->persist($prediction);
            }
        }
    }

    /**
     * @return array{0: int, 1: int, 2: string}
     */
    private function predictedOutcome(FootballMatch $match, int $playerIndex): array
    {
        $actualHome = (int) $match->getHomeScore();
        $actualAway = (int) $match->getAwayScore();
        $winnerSide = $match->getAdvancingSide() ?? FootballMatch::SIDE_HOME;
        $loserSide = $winnerSide === FootballMatch::SIDE_HOME ? FootballMatch::SIDE_AWAY : FootballMatch::SIDE_HOME;

        return match ($playerIndex) {
            // Anne: alles perfect.
            0 => [$actualHome, $actualAway, $winnerSide],
            // Bram: juiste 'voor', mis op 'tegen', juiste winnaar.
            1 => [$actualHome, $actualAway + 1, $winnerSide],
            // Chris: beide scores mis, verkeerde winnaar.
            2 => [$actualHome + 1, $actualAway + 1, $loserSide],
            // Diana: exacte uitslag; vanaf de kwartfinales ook de winnaar goed (stijgt in het klassement).
            default => [
                $actualHome,
                $actualAway,
                $match->getRound()?->getName() === 'Kwartfinales' ? $winnerSide : $loserSide,
            ],
        };
    }
}
