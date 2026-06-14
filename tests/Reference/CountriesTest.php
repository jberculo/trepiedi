<?php

namespace App\Tests\Reference;

use App\Reference\Countries;
use PHPUnit\Framework\TestCase;

class CountriesTest extends TestCase
{
    public function testCodeForNameResolvesDutchName(): void
    {
        $this->assertSame('nl', Countries::codeForName('Nederland'));
        $this->assertSame('de', Countries::codeForName('Duitsland'));
    }

    public function testCodeForNameResolvesEnglishName(): void
    {
        $this->assertSame('nl', Countries::codeForName('Netherlands'));
        $this->assertSame('gb-eng', Countries::codeForName('England'));
    }

    public function testCodeForNameIsCaseAndWhitespaceInsensitive(): void
    {
        $this->assertSame('be', Countries::codeForName('  belgië '));
        $this->assertSame('br', Countries::codeForName('BRAZILIË'));
    }

    public function testCodeForNameReturnsNullForUnknownOrEmpty(): void
    {
        $this->assertNull(Countries::codeForName('Winnaar 16e 1'));
        $this->assertNull(Countries::codeForName(''));
        $this->assertNull(Countries::codeForName(null));
    }

    public function testSearchMatchesPrefixInDutch(): void
    {
        $results = Countries::search('ne', 'nl');
        $names = array_column($results, 'name');

        $this->assertContains('Nederland', $names);
        // Resultaten bevatten een naam en een flag-icons-code.
        $this->assertArrayHasKey('code', $results[0]);
    }

    public function testSearchReturnsLocalisedNames(): void
    {
        $results = Countries::search('neth', 'en');
        $this->assertSame('Netherlands', $results[0]['name']);
        $this->assertSame('nl', $results[0]['code']);
    }

    public function testSearchIsLimitedAndEmptyForBlankQuery(): void
    {
        $this->assertSame([], Countries::search('', 'nl'));
        $this->assertLessThanOrEqual(10, count(Countries::search('a', 'nl')));
    }
}
