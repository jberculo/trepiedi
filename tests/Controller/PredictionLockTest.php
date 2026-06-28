<?php

namespace App\Tests\Controller;

use App\Entity\FootballMatch;
use App\Entity\Prediction;
use App\Tests\FixturesWebTestCase;

class PredictionLockTest extends FixturesWebTestCase
{
    public function testPredictionOnStartedMatchIsRejected(): void
    {
        $match = $this->lockedMatch();
        $matchId = $match->getId();
        $bramId = $this->user('bram@trepiedi.test')->getId();

        $this->client->loginUser($this->user('bram@trepiedi.test'));
        $this->client->request('POST', '/voorspelling/' . $matchId . '/opslaan', [
            'prediction' => [
                'homeScore' => 9,
                'awayScore' => 9,
                'advancingSide' => 'home',
            ],
        ]);

        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertSelectorTextContains('.alert-danger', 'gestart');

        // De bestaande voorspelling mag niet zijn overschreven met 9-9.
        $this->em->clear();
        $prediction = $this->em->getRepository(Prediction::class)
            ->findOneBy(['user' => $bramId, 'footballMatch' => $matchId]);
        $this->assertNotNull($prediction);
        $this->assertNotSame(9, $prediction->getHomeScore(), 'Voorspelling op gestarte wedstrijd is toch gewijzigd.');
    }

    public function testInactiveMatchIsNotPredictable(): void
    {
        $match = $this->openMatch();
        $match->setActive(false);
        $this->em->flush();
        $matchId = $match->getId();

        $this->client->loginUser($this->user('bram@trepiedi.test'));
        $this->client->request('POST', '/voorspelling/' . $matchId . '/opslaan', [
            'prediction' => [
                'homeScore' => 1,
                'awayScore' => 0,
                'advancingSide' => 'home',
            ],
        ]);

        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertSelectorTextContains('.alert-danger', 'nog niet beschikbaar');

        $this->em->clear();
        $count = $this->em->getRepository(Prediction::class)->count(['footballMatch' => $matchId]);
        $this->assertSame(0, $count, 'Een inactieve wedstrijd mag niet invulbaar zijn.');
    }

    public function testPredictionCanBeUpdated(): void
    {
        $match = $this->openMatch();
        $matchId = $match->getId();
        $bramId = $this->user('bram@trepiedi.test')->getId();

        $this->client->loginUser($this->user('bram@trepiedi.test'));

        $crawler = $this->client->request('GET', '/voorspellen');
        $form = $crawler->filter('form[action$="/voorspelling/' . $matchId . '/opslaan"]')->form([
            'prediction[homeScore]' => '1',
            'prediction[awayScore]' => '1',
            'prediction[advancingSide]' => 'home',
        ]);
        $this->client->submit($form);

        // Opnieuw, met andere waarden.
        $crawler = $this->client->request('GET', '/voorspellen');
        $form = $crawler->filter('form[action$="/voorspelling/' . $matchId . '/opslaan"]')->form([
            'prediction[homeScore]' => '3',
            'prediction[awayScore]' => '2',
            'prediction[advancingSide]' => 'home',
        ]);
        $this->client->submit($form);

        $this->em->clear();
        $repo = $this->em->getRepository(Prediction::class);
        $this->assertSame(1, $repo->count(['user' => $bramId, 'footballMatch' => $matchId]), 'Voorspelling mag niet dubbel.');
        $prediction = $repo->findOneBy(['user' => $bramId, 'footballMatch' => $matchId]);
        $this->assertSame(3, $prediction->getHomeScore());
        $this->assertSame(2, $prediction->getAwayScore());
    }

    public function testPredictionRequiresWinner(): void
    {
        $match = $this->openMatch();
        $matchId = $match->getId();

        $this->client->loginUser($this->user('bram@trepiedi.test'));
        $crawler = $this->client->request('GET', '/voorspellen');
        $form = $crawler->filter('form[action$="/voorspelling/' . $matchId . '/opslaan"]')->form();
        $form['prediction[homeScore]'] = '2';
        $form['prediction[awayScore]'] = '1';
        $form['prediction[advancingSide]'] = ''; // geen winnaar gekozen
        $this->client->submit($form);

        $this->em->clear();
        $count = $this->em->getRepository(Prediction::class)->count(['footballMatch' => $matchId]);
        $this->assertSame(0, $count, 'Zonder winnaar mag er niets worden opgeslagen.');
    }

    public function testPredictionIsSavedViaAjax(): void
    {
        $match = $this->openMatch();
        $matchId = $match->getId();
        $bramId = $this->user('bram@trepiedi.test')->getId();

        $this->client->loginUser($this->user('bram@trepiedi.test'));
        $crawler = $this->client->request('GET', '/voorspellen');
        $form = $crawler->filter('form[action$="/voorspelling/' . $matchId . '/opslaan"]')->form([
            'prediction[homeScore]' => '2',
            'prediction[awayScore]' => '0',
            'prediction[advancingSide]' => 'home',
        ]);

        // Versturen als AJAX: verwacht JSON, geen redirect.
        $this->client->xmlHttpRequest('POST', $form->getUri(), $form->getPhpValues());

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['ok']);

        $this->em->clear();
        $prediction = $this->em->getRepository(Prediction::class)
            ->findOneBy(['user' => $bramId, 'footballMatch' => $matchId]);
        $this->assertNotNull($prediction, 'AJAX-voorspelling is niet opgeslagen.');
        $this->assertSame(2, $prediction->getHomeScore());
    }

    public function testAjaxPredictionOnStartedMatchReturns422(): void
    {
        $match = $this->lockedMatch();
        $matchId = $match->getId();

        $this->client->loginUser($this->user('bram@trepiedi.test'));
        $this->client->xmlHttpRequest('POST', '/voorspelling/' . $matchId . '/opslaan', [
            'prediction' => [
                'homeScore' => 9,
                'awayScore' => 9,
                'advancingSide' => 'home',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertFalse($data['ok']);
    }

    public function testInconsistentPredictionNeedsConfirmation(): void
    {
        $match = $this->openMatch();
        $matchId = $match->getId();
        $bramId = $this->user('bram@trepiedi.test')->getId();

        $this->client->loginUser($this->user('bram@trepiedi.test'));
        $crawler = $this->client->request('GET', '/voorspellen');
        $form = $crawler->filter('form[action$="/voorspelling/' . $matchId . '/opslaan"]')->form([
            'prediction[homeScore]' => '2',
            'prediction[awayScore]' => '3', // uit wint op doelpunten...
            'prediction[advancingSide]' => 'home', // ...maar thuis als doorgaande ploeg → tegenstrijdig
        ]);

        $this->client->xmlHttpRequest('POST', $form->getUri(), $form->getPhpValues());

        $this->assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertFalse($data['ok']);
        $this->assertTrue($data['needsConfirmation'] ?? false, 'Een tegenstrijdige voorspelling vraagt om bevestiging.');

        $this->em->clear();
        $count = $this->em->getRepository(Prediction::class)->count(['user' => $bramId, 'footballMatch' => $matchId]);
        $this->assertSame(0, $count, 'Zonder bevestiging mag de tegenstrijdige voorspelling niet worden opgeslagen.');
    }

    public function testInconsistentPredictionIsSavedAfterConfirmation(): void
    {
        $match = $this->openMatch();
        $matchId = $match->getId();
        $bramId = $this->user('bram@trepiedi.test')->getId();

        $this->client->loginUser($this->user('bram@trepiedi.test'));
        $crawler = $this->client->request('GET', '/voorspellen');
        $form = $crawler->filter('form[action$="/voorspelling/' . $matchId . '/opslaan"]')->form([
            'prediction[homeScore]' => '2',
            'prediction[awayScore]' => '3',
            'prediction[advancingSide]' => 'home',
        ]);

        $values = $form->getPhpValues();
        $values['confirm_inconsistent'] = '1';
        $this->client->xmlHttpRequest('POST', $form->getUri(), $values);

        $this->assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['ok'], 'Na bevestiging wordt de tegenstrijdige voorspelling opgeslagen.');

        $this->em->clear();
        $prediction = $this->em->getRepository(Prediction::class)
            ->findOneBy(['user' => $bramId, 'footballMatch' => $matchId]);
        $this->assertNotNull($prediction);
        $this->assertSame(2, $prediction->getHomeScore());
        $this->assertSame(3, $prediction->getAwayScore());
        $this->assertSame(FootballMatch::SIDE_HOME, $prediction->getAdvancingSide());
    }

    public function testDrawPredictionIsNotInconsistent(): void
    {
        $match = $this->openMatch();
        $matchId = $match->getId();
        $bramId = $this->user('bram@trepiedi.test')->getId();

        $this->client->loginUser($this->user('bram@trepiedi.test'));
        $crawler = $this->client->request('GET', '/voorspellen');
        // Gelijkspel met een doorgaande ploeg (penalty's) is niet tegenstrijdig.
        $form = $crawler->filter('form[action$="/voorspelling/' . $matchId . '/opslaan"]')->form([
            'prediction[homeScore]' => '1',
            'prediction[awayScore]' => '1',
            'prediction[advancingSide]' => 'home',
        ]);

        $this->client->xmlHttpRequest('POST', $form->getUri(), $form->getPhpValues());

        $this->assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['ok'], 'Een gelijkspel-voorspelling wordt direct opgeslagen.');

        $this->em->clear();
        $count = $this->em->getRepository(Prediction::class)->count(['user' => $bramId, 'footballMatch' => $matchId]);
        $this->assertSame(1, $count);
    }

    public function testPredictionOnOpenMatchIsSaved(): void
    {
        $match = $this->openMatch();
        $matchId = $match->getId();
        $bramId = $this->user('bram@trepiedi.test')->getId();

        $this->client->loginUser($this->user('bram@trepiedi.test'));
        $crawler = $this->client->request('GET', '/voorspellen');

        $form = $crawler->filter('form[action$="/voorspelling/' . $matchId . '/opslaan"]')->form([
            'prediction[homeScore]' => '3',
            'prediction[awayScore]' => '1',
            'prediction[advancingSide]' => 'home',
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects();

        $this->em->clear();
        $prediction = $this->em->getRepository(Prediction::class)
            ->findOneBy(['user' => $bramId, 'footballMatch' => $matchId]);
        $this->assertNotNull($prediction, 'Voorspelling op open wedstrijd is niet opgeslagen.');
        $this->assertSame(3, $prediction->getHomeScore());
        $this->assertSame(1, $prediction->getAwayScore());
    }
}
