<?php

namespace App\Tests\Controller;

use App\Entity\FootballMatch;
use App\Entity\Round;
use App\Tests\FixturesWebTestCase;

class AdminCrudTest extends FixturesWebTestCase
{
    public function testAdminCanCreateRound(): void
    {
        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $crawler = $this->client->request('GET', '/admin/ronden/nieuw');

        $form = $crawler->selectButton('Opslaan')->form([
            'round[name]' => 'Testronde',
            'round[sortOrder]' => '9',
            'round[weight]' => '1',
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/ronden');
        $this->assertNotNull($this->em->getRepository(Round::class)->findOneBy(['name' => 'Testronde']));
    }

    public function testAdminCanCreateMatch(): void
    {
        $round = $this->em->getRepository(Round::class)->findOneBy(['name' => 'Achtste finales']);
        $before = $this->em->getRepository(FootballMatch::class)->count([]);

        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $crawler = $this->client->request('GET', '/admin/wedstrijden/nieuw');

        // Ploegen zijn nu vrije tekst, geen team-dropdown.
        $form = $crawler->selectButton('Opslaan')->form();
        $form['football_match[round]'] = (string) $round->getId();
        $form['football_match[homeTeam]'] = 'Testland';
        $form['football_match[awayTeam]'] = 'Andersland';
        $form['football_match[kickoffAt]'] = '2026-07-01T20:00';
        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/wedstrijden');
        $this->em->clear();
        $this->assertSame($before + 1, $this->em->getRepository(FootballMatch::class)->count([]));
        $this->assertNotNull(
            $this->em->getRepository(FootballMatch::class)->findOneBy(['homeTeam' => 'Testland']),
            'Wedstrijd met de getypte ploegnaam is niet opgeslagen.'
        );
    }

    public function testRegularUserCannotAccessAdmin(): void
    {
        $this->client->loginUser($this->user('anne@trepiedi.test'));
        $this->client->request('GET', '/admin/wedstrijden');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdminCanEnterResult(): void
    {
        $open = $this->openMatch();
        $matchId = $open->getId();

        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $crawler = $this->client->request('GET', '/admin/wedstrijden/' . $matchId . '/uitslag');

        $form = $crawler->selectButton('Opslaan')->form();
        $form['match_result[homeScore]'] = '2';
        $form['match_result[awayScore]'] = '0';
        $form['match_result[advancingSide]'] = 'home';
        $form['match_result[finished]']->tick();
        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/wedstrijden');

        $this->em->clear();
        $match = $this->em->getRepository(FootballMatch::class)->find($matchId);
        $this->assertTrue($match->isFinished());
        $this->assertSame(2, $match->getHomeScore());
        $this->assertSame(0, $match->getAwayScore());
        $this->assertSame(FootballMatch::SIDE_HOME, $match->getAdvancingSide());
    }

    public function testBulkDeactivateMatches(): void
    {
        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $crawler = $this->client->request('GET', '/admin/wedstrijden');
        $token = $crawler->filter('#bulk-matches input[name="_token"]')->attr('value');

        $matches = $this->em->getRepository(FootballMatch::class)->findAll();
        $ids = [$matches[0]->getId(), $matches[1]->getId()];

        $this->client->request('POST', '/admin/wedstrijden/bulk', [
            '_token' => $token,
            'ids' => array_map('strval', $ids),
            'active' => '0',
        ]);
        $this->assertResponseRedirects('/admin/wedstrijden');

        $this->em->clear();
        foreach ($ids as $id) {
            $this->assertFalse(
                $this->em->getRepository(FootballMatch::class)->find($id)->isActive(),
                'Wedstrijd had via de bulk-actie inactief moeten worden.'
            );
        }
    }

    public function testFinalResultRequiresWinner(): void
    {
        $open = $this->openMatch();
        $matchId = $open->getId();

        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $crawler = $this->client->request('GET', '/admin/wedstrijden/' . $matchId . '/uitslag');

        // Definitief aanvinken zonder winnaar te kiezen → afgewezen.
        $form = $crawler->selectButton('Opslaan')->form();
        $form['match_result[homeScore]'] = '1';
        $form['match_result[awayScore]'] = '1';
        $form['match_result[finished]']->tick();
        $this->client->submit($form);

        // Geen redirect: het formulier wordt opnieuw getoond met de fout.
        $this->assertResponseStatusCodeSame(422);

        $this->em->clear();
        $match = $this->em->getRepository(FootballMatch::class)->find($matchId);
        $this->assertFalse($match->isFinished(), 'Wedstrijd zonder winnaar mag niet definitief worden.');
    }
}
