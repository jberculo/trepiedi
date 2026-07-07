<?php

namespace App\Tests\Controller;

use App\Entity\FootballMatch;
use App\Tests\FixturesWebTestCase;

class DashboardFilterTest extends FixturesWebTestCase
{
    public function testFilterCheckboxesAreRenderedAndCheckedByDefault(): void
    {
        $this->client->loginUser($this->user('bram@trepiedi.test'));
        $crawler = $this->client->request('GET', '/voorspellen');
        $this->assertResponseIsSuccessful();

        $boxes = $crawler->filter('input[type="checkbox"][data-filter]');
        $filters = $boxes->each(static fn ($node) => $node->attr('data-filter'));
        sort($filters);
        $this->assertSame(['current', 'finished', 'soon'], $filters);

        // Standaard staat alles aan.
        foreach ($boxes as $box) {
            $this->assertNotNull($box->getAttribute('checked'), 'Elke filter-checkbox staat standaard aan.');
        }
    }

    public function testFinishedAndCurrentMatchesGetTheRightCategory(): void
    {
        $finished = $this->em->getRepository(FootballMatch::class)->findOneBy(['finished' => true]);
        $open = $this->openMatch();

        $this->client->loginUser($this->user('bram@trepiedi.test'));
        $crawler = $this->client->request('GET', '/voorspellen');

        $this->assertSame(
            'finished',
            $crawler->filter('#match-' . $finished->getId())->attr('data-match-category'),
            'Een gespeelde wedstrijd hoort in "uitslagen bekend".'
        );
        $this->assertSame(
            'current',
            $crawler->filter('#match-' . $open->getId())->attr('data-match-category'),
            'Een actieve toekomstige wedstrijd is "nog te voorspellen en actief".'
        );
    }

    public function testInactiveMatchIsCategorisedAsNotYetPredictable(): void
    {
        $open = $this->openMatch();
        $open->setActive(false);
        $this->em->flush();

        $this->client->loginUser($this->user('bram@trepiedi.test'));
        $crawler = $this->client->request('GET', '/voorspellen');

        $this->assertSame(
            'soon',
            $crawler->filter('#match-' . $open->getId())->attr('data-match-category'),
            'Een inactieve wedstrijd is "nog niet te voorspellen".'
        );
    }
}
