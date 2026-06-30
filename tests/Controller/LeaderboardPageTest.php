<?php

namespace App\Tests\Controller;

use App\Entity\Round;
use App\Tests\FixturesWebTestCase;

class LeaderboardPageTest extends FixturesWebTestCase
{
    public function testDefaultLeaderboardShowsRawPoints(): void
    {
        $crawler = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        // Standaard staan alle gewichten op 1; de kop toont dat expliciet.
        $this->assertSelectorTextContains('thead', '× 1');

        // Anne (perfect) heeft 12 × 6 = 72 punten.
        $anneRow = $crawler->filter('tbody tr')->reduce(
            static fn ($node): bool => str_contains($node->text(), 'Anne')
        );
        $this->assertStringContainsString('72', $anneRow->text());
    }

    public function testThreeRankingsHaveOwnUrl(): void
    {
        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
        // Drie tabbladen met hun wielertrui-namen, elk een eigen link.
        $this->assertSelectorTextContains('.nav-tabs', 'Algemeen klassement');
        $this->assertSelectorTextContains('.nav-tabs', 'Balletjestrui');
        $this->assertSelectorTextContains('.nav-tabs', 'glazen bal');

        // Balletjestrui heeft een eigen URL: koploper heeft 36 onderdelen goed.
        $crawler = $this->client->request('GET', '/balletjestrui');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('36', $crawler->filter('tbody tr')->first()->text());
        // Nu mogelijk = 12 gespeeld × 3 = 36; hele toernooi = 15 wedstrijden × 3 = 45.
        $this->assertStringContainsString('nu mogelijk: 36', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('hele toernooi: 45', $this->client->getResponse()->getContent());

        // Glazen bal heeft een eigen URL: koploper had 12/12 winnaars goed.
        $crawler = $this->client->request('GET', '/glazen-bal');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('12/12', $crawler->filter('tbody tr')->first()->text());
        // Nu mogelijk = 12 gespeeld; hele toernooi = 15 wedstrijden (1 punt per winnaar).
        $this->assertStringContainsString('nu mogelijk: 12', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('hele toernooi: 15', $this->client->getResponse()->getContent());
    }

    public function testLanternAndInconsistentTabsHaveOwnUrl(): void
    {
        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.nav-tabs', 'ronde lantaarn');
        $this->assertSelectorTextContains('.nav-tabs', 'Tegenstrijdig');

        // Ronde lantaarn: Chris zat het vaakst mis (12 strafpunten) en staat bovenaan.
        $crawler = $this->client->request('GET', '/ronde-lantaarn');
        $this->assertResponseIsSuccessful();
        $lanternTop = $crawler->filter('tbody tr')->first();
        $this->assertStringContainsString('Chris', $lanternTop->text());
        $this->assertStringContainsString('12', $lanternTop->text());

        // Tegenstrijdig: Chris staat met 9 bovenaan.
        $crawler = $this->client->request('GET', '/tegenstrijdig');
        $this->assertResponseIsSuccessful();
        $top = $crawler->filter('tbody tr')->first();
        $this->assertStringContainsString('Chris', $top->text());
        $this->assertStringContainsString('9', $top->text());
    }

    public function testPenaltyRankingsColourTheMovementArrowDifferently(): void
    {
        // Ronde lantaarn = straf-klassement: stijgen (richting plek 1) is rood,
        // dus de omhoog-pijl in de legenda is text-danger.
        $this->client->request('GET', '/ronde-lantaarn');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString(
            '<span class="text-danger">&#9650;</span>/<span class="rise">&#9660;</span>',
            (string) $this->client->getResponse()->getContent(),
            'Lantaarn: omhoog = rood (omgekeerd).'
        );

        // Tegenstrijdig: stijgen is juist groen, dus de omhoog-pijl is rise.
        $this->client->request('GET', '/tegenstrijdig');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString(
            '<span class="rise">&#9650;</span>/<span class="text-danger">&#9660;</span>',
            (string) $this->client->getResponse()->getContent(),
            'Tegenstrijdig: omhoog = groen (normaal).'
        );
    }

    /**
     * Alle vijf ranglijst-tabbladen delen hetzelfde tabel-skelet (de embed):
     * een lb-table met #/speler/totaal-koppen en minstens één spelersrij.
     */
    public function testEveryRankingTabRendersTheSharedTable(): void
    {
        foreach (['/', '/balletjestrui', '/glazen-bal', '/ronde-lantaarn', '/tegenstrijdig'] as $url) {
            $crawler = $this->client->request('GET', $url);
            $this->assertResponseIsSuccessful();

            $table = $crawler->filter('table.lb-table');
            $this->assertCount(1, $table, "Tab {$url} toont precies één klassementtabel.");
            $this->assertStringContainsString('Anne', $table->filter('tbody')->text(), "Tab {$url} toont spelersrijen.");
        }
    }

    public function testTiedRanksShareMedalColour(): void
    {
        $crawler = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
        $html = $crawler->filter('table.lb-table')->first()->html();

        // Anne staat met 72 punten alleen op 1 → één keer goud.
        $this->assertSame(1, substr_count($html, 'medal-gold'));
        // Bram en Diana delen plek 2 (allebei 48) → twee keer zilver.
        $this->assertSame(2, substr_count($html, 'medal-silver'));
        // Door de gedeelde 2 staat niemand op 3, dus geen brons.
        $this->assertSame(0, substr_count($html, 'medal-bronze'));
    }

    public function testAnimationTabHasTimelineData(): void
    {
        $crawler = $this->client->request('GET', '/animatie');
        $this->assertResponseIsSuccessful();

        $this->assertSelectorTextContains('.nav-tabs', 'Animatie');
        $this->assertSelectorExists('#anim-board');

        // De tijdlijn bevat alle 12 gespeelde wedstrijden, met punten per klassement.
        $data = json_decode($crawler->filter('#anim-data')->text(), true);
        $this->assertCount(12, $data['steps']);
        $this->assertNotEmpty($data['players']);
        $this->assertCount(count($data['players']), $data['steps'][0]['points']);
        $this->assertArrayHasKey('score', $data['steps'][0]);
        $this->assertArrayHasKey('winners', $data['steps'][0]);
        $this->assertArrayHasKey('lantern', $data['steps'][0]);
        $this->assertArrayHasKey('inconsistent', $data['steps'][0]);
    }

    public function testRoundWeightIsShownAndAppliedOnLeaderboard(): void
    {
        $this->em->getRepository(Round::class)->findOneBy(['name' => 'Kwartfinales'])->setWeight(3.0);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        // Het rondegewicht staat expliciet in de kop.
        $this->assertSelectorTextContains('thead', '× 3');

        // Kwart telt nu × 3: Anne = 8×6×1 + 4×6×3 = 48 + 72 = 120.
        $anneRow = $crawler->filter('tbody tr')->reduce(
            static fn ($node): bool => str_contains($node->text(), 'Anne')
        );
        $this->assertStringContainsString('120', $anneRow->text());
    }
}
