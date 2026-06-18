<?php

namespace App\Tests\Twig;

use App\Flag\FlagProvider;
use App\Twig\FlagExtension;
use PHPUnit\Framework\TestCase;

class FlagExtensionTest extends TestCase
{
    public function testKnownCountryRendersFlagIcon(): void
    {
        $html = (new FlagExtension(new FlagProvider(dirname(__DIR__, 2) . '/assets/flags')))->countryFlag('Nederland');

        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('title="Nederland"', $html);
        $this->assertStringNotContainsString('team-flag-unknown', $html);
    }

    public function testUnknownNameRendersGreyQuestionMark(): void
    {
        $html = (new FlagExtension(new FlagProvider(dirname(__DIR__, 2) . '/assets/flags')))->countryFlag('Winnaar 16e 1');

        $this->assertStringContainsString('team-flag-unknown', $html);
        $this->assertStringContainsString('>?<', $html);
    }

    public function testNameIsHtmlEscapedInTitle(): void
    {
        $html = (new FlagExtension(new FlagProvider(dirname(__DIR__, 2) . '/assets/flags')))->countryFlag('<script>');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
