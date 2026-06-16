# Trepiedi MCP-server

Een [MCP](https://modelcontextprotocol.io)-server die de Trepiedi-API ontsluit, zodat een AI-assistent (zoals Claude) de stand kan opvragen en uitslagen kan toevoegen/aanpassen.

## Tools

| Tool | Sleutel nodig? | Wat |
|------|----------------|-----|
| `get_standings(poolCode?)` | nee | Huidige stand van een poule (default: standaardpoule) |
| `list_matches()` | nee | Alle wedstrijden + open/te voorspellen (met id) |
| `get_match(matchId)` | nee | Eén wedstrijd met detail |
| `get_rounds()` | nee | Ronden met gewicht en aantal wedstrijden |
| `whoami()` | ja | Info over de eigenaar van de sleutel |
| `submit_prediction(matchId, homeScore, awayScore, advancingSide)` | ja | Je eigen voorspelling indienen/aanpassen |
| `set_match_result(matchId, homeScore, awayScore, advancingSide?, finished?)` | ja (beheerder) | Uitslag van een open wedstrijd zetten/aanpassen |
| `update_match(matchId, home?, away?, kickoff?, active?)` | ja (beheerder) | Ploegnamen/aftrap/activeren bijwerken |
| `create_pool(name, code?, default?)` | ja (beheerder) | Een poule aanmaken |

## Installeren

```bash
cd mcp
npm install
```

## Configuratie (environment)

- `TREPIEDI_API_URL` — basis-URL (default `https://trepiedi.online`)
- `TREPIEDI_API_KEY` — je persoonlijke API-sleutel (te vinden in je profiel op de site). Alleen nodig voor `set_match_result`. Lezen kan zonder.

## Koppelen aan een client

Voorbeeld voor `claude_desktop_config.json` of een project-`.mcp.json`:

```json
{
  "mcpServers": {
    "trepiedi": {
      "command": "node",
      "args": ["C:/www/trepiedi/mcp/server.mjs"],
      "env": {
        "TREPIEDI_API_KEY": "jouw-sleutel-uit-je-profiel"
      }
    }
  }
}
```

Daarna kun je vragen als *"wat is de stand in poule kantoor?"* of *"zet de uitslag van wedstrijd 42 op 2-1, thuis gaat door en maak 'm definitief"*.

Schrijven kan alleen met een **beheerders**-sleutel; lezen werkt met elke (of zonder) sleutel.
