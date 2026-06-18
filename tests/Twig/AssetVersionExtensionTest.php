<?php

namespace App\Tests\Twig;

use App\Twig\AssetVersionExtension;
use PHPUnit\Framework\TestCase;

class AssetVersionExtensionTest extends TestCase
{
    public function testExistingFileReturnsMtime(): void
    {
        $dir = sys_get_temp_dir() . '/trepiedi-asset-version-' . uniqid();
        mkdir($dir);
        $file = $dir . '/app.css';
        file_put_contents($file, 'body{}');

        $ext = new AssetVersionExtension($dir);

        $this->assertSame((string) filemtime($file), $ext->assetVersion('app.css'));

        @unlink($file);
        @rmdir($dir);
    }

    public function testMissingFileReturnsZero(): void
    {
        $ext = new AssetVersionExtension(sys_get_temp_dir());

        $this->assertSame('0', $ext->assetVersion('missing.css'));
    }
}
