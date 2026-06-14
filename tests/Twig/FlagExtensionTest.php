<?php

namespace App\Tests\Twig;

use App\Twig\FlagExtension;
use PHPUnit\Framework\TestCase;

class FlagExtensionTest extends TestCase
{
    public function testKnownCountryRendersFlagIcon(): void
    {
        $html = (new FlagExtension())->countryFlag('Nederland');

        $this->assertStringContainsString('fi fi-nl', $html);
        $this->assertStringContainsString('title="Nederland"', $html);
        $this->assertStringNotContainsString('team-flag-unknown', $html);
    }

    public function testUnknownNameRendersGreyQuestionMark(): void
    {
        $html = (new FlagExtension())->countryFlag('Winnaar 16e 1');

        $this->assertStringContainsString('team-flag-unknown', $html);
        $this->assertStringContainsString('>?<', $html);
    }

    public function testNameIsHtmlEscapedInTitle(): void
    {
        $html = (new FlagExtension())->countryFlag('<script>');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
