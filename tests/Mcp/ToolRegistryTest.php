<?php

namespace App\Tests\Mcp;

use App\Api\ApiError;
use App\Api\ApiException;
use App\Api\ReadApi;
use App\Api\WriteApi;
use App\Mcp\ToolRegistry;
use PHPUnit\Framework\TestCase;

/**
 * De MCP-toolregistry: de definities (tools/list) en de dispatch (tools/call)
 * naar dezelfde API-services als de REST-laag.
 */
class ToolRegistryTest extends TestCase
{
    private const EXPECTED_TOOLS = [
        'get_standings', 'list_matches', 'get_match', 'get_rounds', 'whoami',
        'submit_prediction', 'set_match_result', 'update_match', 'list_pools', 'create_pool',
    ];

    public function testDefinitionsListAllTools(): void
    {
        $registry = new ToolRegistry($this->createStub(ReadApi::class), $this->createStub(WriteApi::class));
        $names = array_column($registry->definitions(), 'name');

        $this->assertSame(self::EXPECTED_TOOLS, $names);
    }

    public function testEveryDefinitionHasNameDescriptionAndObjectSchema(): void
    {
        $registry = new ToolRegistry($this->createStub(ReadApi::class), $this->createStub(WriteApi::class));

        foreach ($registry->definitions() as $tool) {
            $this->assertArrayHasKey('name', $tool);
            $this->assertNotSame('', $tool['description']);
            $this->assertSame('object', $tool['inputSchema']['type']);
            $this->assertArrayHasKey('properties', $tool['inputSchema']);
        }
    }

    public function testGetMatchDeclaresMatchIdRequired(): void
    {
        $registry = new ToolRegistry($this->createStub(ReadApi::class), $this->createStub(WriteApi::class));
        $byName = array_column($registry->definitions(), null, 'name');

        $this->assertSame(['matchId'], $byName['get_match']['inputSchema']['required']);
    }

    public function testCallDispatchesToReadApi(): void
    {
        $read = $this->createMock(ReadApi::class);
        $read->expects($this->once())->method('standings')->with('kantoor')->willReturn(['ok' => true]);

        $registry = new ToolRegistry($read, $this->createStub(WriteApi::class));

        $this->assertSame(['ok' => true], $registry->call('get_standings', ['poolCode' => 'kantoor'], null));
    }

    public function testCallDispatchesToWriteApi(): void
    {
        $write = $this->createMock(WriteApi::class);
        $write->expects($this->once())->method('setResult')->willReturn(['done' => true]);

        $registry = new ToolRegistry($this->createStub(ReadApi::class), $write);

        $args = ['matchId' => 7, 'homeScore' => 1, 'awayScore' => 0];
        $this->assertSame(['done' => true], $registry->call('set_match_result', $args, null));
    }

    public function testCallUnknownToolThrowsNotFound(): void
    {
        $registry = new ToolRegistry($this->createStub(ReadApi::class), $this->createStub(WriteApi::class));

        try {
            $registry->call('bestaat_niet', [], null);
            $this->fail('Verwachtte een ApiException.');
        } catch (ApiException $e) {
            $this->assertSame(ApiError::NotFound, $e->error);
        }
    }
}
