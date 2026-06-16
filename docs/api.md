# Trepiedi API

JSON-API van de voetbalpoule. Basis-URL: `https://trepiedi.online`.

## Authenticatie

- **Lezen** (stand, wedstrijden, ronden) kan **zonder sleutel**.
- **Schrijven** gebruikt je persoonlijke API-sleutel via de header **`X-API-Key`**. Je vindt 'm in je profiel (*Account → API-sleutel*) en kunt 'm daar opnieuw genereren.
- Twee niveaus:
  - **eigen sleutel** (elke speler): je eigen voorspelling indienen en `/api/me`;
  - **beheerderssleutel**: uitslagen, wedstrijden bijwerken en poules beheren.

Foutcodes: `401` ontbrekende/ongeldige sleutel, `403` geldige sleutel maar geen beheerder, `404` niet gevonden, `409` actie kan niet in de huidige status, `422` validatiefout.

---

## Lezen (publiek)

### `GET /api/standings` — stand van een poule
Zonder code de standaardpoule; een andere poule via het pad of `?pool=`.

```bash
curl https://trepiedi.online/api/standings
curl https://trepiedi.online/api/standings/kantoor
curl "https://trepiedi.online/api/standings?pool=kantoor"
```

```json
{
  "pool": { "name": "Tremani", "code": "algemeen" },
  "types": [
    { "key": "points", "emoji": "🟡", "label": "Algemeen", "field": "weightedTotal" },
    { "key": "score", "emoji": "⚽", "label": "Balletjestrui", "field": "scorePoints" },
    { "key": "winners", "emoji": "🔮", "label": "Glazen bal", "field": "winners" },
    { "key": "lantern", "emoji": "🔴", "label": "Ronde lantaarn", "field": "lanternPoints" },
    { "key": "inconsistent", "emoji": "🤔", "label": "Tegenstrijdig", "field": "inconsistent" }
  ],
  "standings": [
    { "rank": 1, "movement": 2, "player": "Anne", "slug": "anne",
      "weightedTotal": 84, "rawTotal": 30, "scorePoints": 12,
      "winners": 9, "lanternPoints": 0, "inconsistent": 0 }
  ]
}
```
- `movement` — positieverandering sinds de vorige speeldag (`+` = gestegen, `null` = nieuw/geen vergelijking).
- `types` — de klassement-types met hun emoji en het veld waarop ze sorteren.

### `GET /api/matches` — alle wedstrijden
```bash
curl https://trepiedi.online/api/matches
```
Elk item bevat o.a.:

```json
{
  "id": 42, "round": "16e finales", "kickoff": "2026-06-28T21:00:00+02:00",
  "home": "Nederland", "away": "Polen", "homeFlag": "nl", "awayFlag": "pl",
  "homeScore": 2, "awayScore": 1,
  "advancingTeam": "Nederland", "advancingFlag": "nl", "advancingSide": "home",
  "finished": true, "open": false, "active": true, "locked": true, "predictable": false
}
```
- `homeFlag`/`awayFlag`/`advancingFlag` — [flag-icons](https://github.com/lipis/flag-icons)-code per ploeg (bijv. `nl`, `gb-eng`), of `null` bij een placeholder (`2A`).
- `open` — uitslag nog niet definitief (relevant voor de *uitslag*-write).
- `predictable` — je kunt nu nog een voorspelling indienen/aanpassen (`active` én niet `locked`).

### `GET /api/matches/{id}` — één wedstrijd
Bevat `predictionCount`; na de aftrap ook een `predictions`-lijst.

```bash
curl https://trepiedi.online/api/matches/42
```

### `GET /api/rounds` — ronden
```bash
curl https://trepiedi.online/api/rounds
```
```json
{ "rounds": [ { "name": "16e finales", "sortOrder": 1, "weight": 1, "matchCount": 16 } ] }
```

---

## Met je eigen sleutel

### `GET /api/me` — wie ben ik
```bash
curl -H "X-API-Key: JOUW_SLEUTEL" https://trepiedi.online/api/me
```
```json
{ "displayName": "Anne", "slug": "anne", "admin": false,
  "pools": [ { "name": "Tremani", "code": "algemeen", "default": true, "archived": false } ],
  "activePool": "algemeen" }
```

### `POST /api/matches/{id}/prediction` — je eigen voorspelling
Kan alleen als de wedstrijd `predictable` is. `advancingSide` is verplicht (`home`/`away`).

```bash
curl -X POST https://trepiedi.online/api/matches/42/prediction \
  -H "X-API-Key: JOUW_SLEUTEL" -H "Content-Type: application/json" \
  -d '{"homeScore":2,"awayScore":1,"advancingSide":"home"}'
```
```json
{ "match": 42, "prediction": {"homeScore":2,"awayScore":1,"advancingSide":"home"}, "saved": true }
```

---

## Met een beheerderssleutel

### `POST /api/matches/{id}/result` — uitslag toevoegen/aanpassen
Alleen voor wedstrijden die nog **open** staan (niet definitief). `finished:true` maakt de uitslag definitief en vereist `advancingSide`.

```bash
curl -X POST https://trepiedi.online/api/matches/42/result \
  -H "X-API-Key: BEHEERDER_SLEUTEL" -H "Content-Type: application/json" \
  -d '{"homeScore":2,"awayScore":1,"advancingSide":"home","finished":true}'
```

### `PATCH /api/matches/{id}` — wedstrijd bijwerken
Ploegnamen invullen, activeren/deactiveren of de aftrap aanpassen (bijv. zodra de loting bekend is). Alle velden optioneel.

```bash
curl -X PATCH https://trepiedi.online/api/matches/42 \
  -H "X-API-Key: BEHEERDER_SLEUTEL" -H "Content-Type: application/json" \
  -d '{"home":"Nederland","away":"Brazilië","active":true,"kickoff":"2026-06-28T21:00:00"}'
```

### `GET /api/pools` — poules
```bash
curl -H "X-API-Key: BEHEERDER_SLEUTEL" https://trepiedi.online/api/pools
```

### `POST /api/pools` — poule aanmaken
`code` is optioneel (anders gegenereerd uit de naam). `default:true` maakt het de standaardpoule.

```bash
curl -X POST https://trepiedi.online/api/pools \
  -H "X-API-Key: BEHEERDER_SLEUTEL" -H "Content-Type: application/json" \
  -d '{"name":"Vrienden"}'
```
```json
{ "name": "Vrienden", "code": "vrienden-7b2e", "default": false }
```

---

## MCP-server (`/mcp`)

`https://trepiedi.online/mcp` is een **MCP-server** (Streamable HTTP, JSON-RPC) die dezelfde functionaliteit als tools voor een AI-assistent aanbiedt — zelfde logica, zelfde `X-API-Key`-auth. Koppelen, bijv. in Claude Code:

```bash
claude mcp add --transport http trepiedi https://trepiedi.online/mcp --header "X-API-Key: jouw-sleutel"
```

Tools: `get_standings`, `list_matches`, `get_match`, `get_rounds`, `whoami`, `submit_prediction`, `set_match_result`, `update_match`, `list_pools`, `create_pool`. Lezen kan zonder sleutel; schrijven met je (beheerders)sleutel. Zie [`mcp/README.md`](../mcp/README.md) voor de hosted-koppeling en een lokale stdio-variant.
