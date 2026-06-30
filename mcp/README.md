# Trepiedi MCP-server

Een [MCP](https://modelcontextprotocol.io)-server die de Trepiedi-API ontsluit, zodat een AI-assistent (zoals Claude) de stand kan opvragen, je eigen voorspelling kan indienen en — als beheerder — uitslagen/wedstrijden/poules kan beheren.

Er zijn twee manieren om 'm te gebruiken. **De hosted variant is aanbevolen** (niets installeren).

## 1. Hosted (aanbevolen) — `https://trepiedi.online/mcp`

De server draait al binnen de app op `/mcp` (Streamable HTTP). Koppelen in Claude Code:

```bash
claude mcp add --transport http trepiedi https://trepiedi.online/mcp --header "X-API-Key: jouw-sleutel"
```

Of in een client-config (Claude Desktop / `.mcp.json`):

```json
{
  "mcpServers": {
    "trepiedi": {
      "type": "http",
      "url": "https://trepiedi.online/mcp",
      "headers": { "X-API-Key": "jouw-sleutel" }
    }
  }
}
```

Je sleutel staat in je profiel (*Account → API-sleutel*). Lezen kan zonder sleutel; voor je eigen voorspelling en beheer-acties is 'm nodig.

## 2. Lokaal (stdio) — alternatief / dev

De Node-server in deze map praat met dezelfde API over stdio.

```bash
cd mcp
npm install
```
```json
{
  "mcpServers": {
    "trepiedi": {
      "command": "node",
      "args": ["C:/www/trepiedi/mcp/server.mjs"],
      "env": { "TREPIEDI_API_KEY": "jouw-sleutel" }
    }
  }
}
```
Env: `TREPIEDI_API_URL` (default `https://trepiedi.online`), `TREPIEDI_API_KEY` (alleen voor schrijven).

## Tools

| Tool | Sleutel nodig? | Wat |
|------|----------------|-----|
| `get_standings(poolCode?)` | nee | Stand van een poule, per klassement een gesorteerde lijst met rang, waarde en movement (+ types/emoji) |
| `list_matches()` | nee | Alle wedstrijden + open/te voorspellen (met id) |
| `get_match(matchId)` | nee | Eén wedstrijd met detail |
| `get_rounds()` | nee | Ronden met gewicht en aantal wedstrijden |
| `whoami()` | ja | Info over de eigenaar van de sleutel |
| `submit_prediction(matchId, homeScore, awayScore, advancingSide)` | ja | Je eigen voorspelling indienen/aanpassen |
| `set_match_result(matchId, homeScore, awayScore, advancingSide?, finished?)` | ja (beheerder) | Uitslag van een open wedstrijd zetten |
| `update_match(matchId, home?, away?, kickoff?, active?)` | ja (beheerder) | Ploegnamen/aftrap/activeren bijwerken |
| `list_pools()` | ja (beheerder) | Alle poules |
| `create_pool(name, code?, default?)` | ja (beheerder) | Een poule aanmaken |

Zie ook de volledige API-documentatie in [`../docs/api.md`](../docs/api.md).
