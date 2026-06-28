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

    public function testAdminCanEditRound(): void
    {
        $round = $this->em->getRepository(Round::class)->findOneBy(['name' => 'Achtste finales']);
        $id = $round->getId();

        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $crawler = $this->client->request('GET', '/admin/ronden/' . $id . '/bewerken');
        $form = $crawler->selectButton('Opslaan')->form(['round[name]' => 'Achtste (gewijzigd)']);
        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/ronden');
        $this->em->clear();
        $this->assertSame('Achtste (gewijzigd)', $this->em->getRepository(Round::class)->find($id)->getName());
    }

    public function testAdminCanDeleteRound(): void
    {
        // Lege ronde (zonder wedstrijden) om te verwijderen.
        $round = (new Round())->setName('Weg ermee')->setSortOrder(99)->setWeight(1.0);
        $this->em->persist($round);
        $this->em->flush();
        $id = $round->getId();

        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $crawler = $this->client->request('GET', '/admin/ronden');
        $form = $crawler->filter('form[action="/admin/ronden/' . $id . '/verwijderen"]')->form();
        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/ronden');
        $this->em->clear();
        $this->assertNull($this->em->getRepository(Round::class)->find($id), 'Ronde is verwijderd.');
    }

    public function testDeleteRoundRejectsInvalidCsrf(): void
    {
        $round = (new Round())->setName('Blijft staan')->setSortOrder(98)->setWeight(1.0);
        $this->em->persist($round);
        $this->em->flush();
        $id = $round->getId();

        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $this->client->request('POST', '/admin/ronden/' . $id . '/verwijderen', ['_token' => 'ongeldig']);
        $this->assertResponseRedirects('/admin/ronden');

        $this->em->clear();
        $this->assertNotNull($this->em->getRepository(Round::class)->find($id), 'Bij ongeldige CSRF blijft de ronde staan.');
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
        $form['football_match[kickoffAt]'] = (new \DateTimeImmutable('+1 month'))->format('Y-m-d\TH:i');
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

    public function testBackendResultChangeClearsApiFlag(): void
    {
        $open = $this->openMatch();
        $open->setHomeScore(1)->setAwayScore(0)->setAdvancingSide(FootballMatch::SIDE_HOME)->setResultViaExternalApi(true);
        $this->em->flush();
        $matchId = $open->getId();

        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $crawler = $this->client->request('GET', '/admin/wedstrijden/' . $matchId . '/uitslag');

        $form = $crawler->selectButton('Opslaan')->form();
        $form['match_result[homeScore]'] = '3';
        $form['match_result[awayScore]'] = '0';
        $form['match_result[advancingSide]'] = 'home';
        $this->client->submit($form);
        $this->assertResponseRedirects('/admin/wedstrijden');

        $this->em->clear();
        $match = $this->em->getRepository(FootballMatch::class)->find($matchId);
        $this->assertFalse($match->isResultViaExternalApi(), 'Een afwijkende backend-wijziging haalt de API/MCP-vlag weg.');
    }

    public function testBackendMarkingFinishedClearsApiFlag(): void
    {
        $open = $this->openMatch();
        $open->setHomeScore(1)->setAwayScore(0)->setAdvancingSide(FootballMatch::SIDE_HOME)->setResultViaExternalApi(true);
        $this->em->flush();
        $matchId = $open->getId();

        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $crawler = $this->client->request('GET', '/admin/wedstrijden/' . $matchId . '/uitslag');

        // Zelfde score, maar nu definitief maken: ook dat is een uitslag-aanpassing.
        $form = $crawler->selectButton('Opslaan')->form();
        $form['match_result[homeScore]'] = '1';
        $form['match_result[awayScore]'] = '0';
        $form['match_result[advancingSide]'] = 'home';
        $form['match_result[finished]']->tick();
        $this->client->submit($form);
        $this->assertResponseRedirects('/admin/wedstrijden');

        $this->em->clear();
        $match = $this->em->getRepository(FootballMatch::class)->find($matchId);
        $this->assertTrue($match->isFinished());
        $this->assertFalse($match->isResultViaExternalApi(), 'Handmatig definitief maken haalt de API/MCP-vlag weg.');
    }

    public function testBackendResultSaveWithoutChangeKeepsApiFlag(): void
    {
        $open = $this->openMatch();
        $open->setHomeScore(1)->setAwayScore(0)->setAdvancingSide(FootballMatch::SIDE_HOME)->setResultViaExternalApi(true);
        $this->em->flush();
        $matchId = $open->getId();

        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $crawler = $this->client->request('GET', '/admin/wedstrijden/' . $matchId . '/uitslag');

        // Dezelfde uitslag opnieuw opslaan mag de vlag niet wijzigen.
        $form = $crawler->selectButton('Opslaan')->form();
        $form['match_result[homeScore]'] = '1';
        $form['match_result[awayScore]'] = '0';
        $form['match_result[advancingSide]'] = 'home';
        $this->client->submit($form);
        $this->assertResponseRedirects('/admin/wedstrijden');

        $this->em->clear();
        $match = $this->em->getRepository(FootballMatch::class)->find($matchId);
        $this->assertTrue($match->isResultViaExternalApi(), 'Opnieuw opslaan zonder wijziging laat de vlag staan.');
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

    public function testInconsistentResultNeedsConfirmation(): void
    {
        $open = $this->openMatch();
        $matchId = $open->getId();

        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $crawler = $this->client->request('GET', '/admin/wedstrijden/' . $matchId . '/uitslag');

        // Thuis wint de score (2-1), maar de uitploeg wordt als doorgaand gekozen.
        $form = $crawler->selectButton('Opslaan')->form();
        $form['match_result[homeScore]'] = '2';
        $form['match_result[awayScore]'] = '1';
        $form['match_result[advancingSide]'] = 'away';
        $this->client->submit($form);

        // Geen redirect, maar een bevestigingsmelding; nog niets opgeslagen.
        $this->assertSelectorExists('.alert-warning');
        $this->assertSelectorExists('input[name="confirm_inconsistent"]');

        $this->em->clear();
        $match = $this->em->getRepository(FootballMatch::class)->find($matchId);
        $this->assertNull($match->getHomeScore(), 'Tegenstrijdige uitslag mag niet zonder bevestiging worden opgeslagen.');
    }

    public function testInconsistentResultIsSavedAfterConfirmation(): void
    {
        $open = $this->openMatch();
        $matchId = $open->getId();

        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $crawler = $this->client->request('GET', '/admin/wedstrijden/' . $matchId . '/uitslag');

        $form = $crawler->selectButton('Opslaan')->form();
        $form['match_result[homeScore]'] = '2';
        $form['match_result[awayScore]'] = '1';
        $form['match_result[advancingSide]'] = 'away';
        $crawler = $this->client->submit($form);

        // De beheerder bevestigt via "Toch opslaan".
        $confirm = $crawler->selectButton('Toch opslaan')->form();
        $this->client->submit($confirm);

        $this->assertResponseRedirects('/admin/wedstrijden');

        $this->em->clear();
        $match = $this->em->getRepository(FootballMatch::class)->find($matchId);
        $this->assertSame(2, $match->getHomeScore());
        $this->assertSame(1, $match->getAwayScore());
        $this->assertSame(FootballMatch::SIDE_AWAY, $match->getAdvancingSide(), 'Na bevestiging wordt de tegenstrijdige uitslag opgeslagen.');
    }

    public function testConsistentResultSavesWithoutConfirmation(): void
    {
        $open = $this->openMatch();
        $matchId = $open->getId();

        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $crawler = $this->client->request('GET', '/admin/wedstrijden/' . $matchId . '/uitslag');

        // Score-winnaar (thuis) gaat ook door → geen bevestiging nodig.
        $form = $crawler->selectButton('Opslaan')->form();
        $form['match_result[homeScore]'] = '2';
        $form['match_result[awayScore]'] = '1';
        $form['match_result[advancingSide]'] = 'home';
        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/wedstrijden');

        $this->em->clear();
        $match = $this->em->getRepository(FootballMatch::class)->find($matchId);
        $this->assertSame(2, $match->getHomeScore());
        $this->assertSame(FootballMatch::SIDE_HOME, $match->getAdvancingSide());
    }
}
