<?php

namespace App\Tests\Notice;

use App\Notice\NoticeType;
use PHPUnit\Framework\TestCase;

class NoticeTypeTest extends TestCase
{
    public function testBootstrapClassMapsToColour(): void
    {
        // info = groen, warning = oranje, error = rood.
        $this->assertSame('success', NoticeType::Info->bootstrapClass());
        $this->assertSame('warning', NoticeType::Warning->bootstrapClass());
        $this->assertSame('danger', NoticeType::Error->bootstrapClass());
    }

    public function testLabelIsTranslationKey(): void
    {
        $this->assertSame('admin.notice_type_info', NoticeType::Info->label());
        $this->assertSame('admin.notice_type_error', NoticeType::Error->label());
    }
}
