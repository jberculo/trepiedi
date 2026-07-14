# Trepiedi API

JSON-API van de voetbalpoule. Basis-URL: `https://trepiedi.online`.

## Authenticatie

- **Lezen** (stand, wedstrijden, ronden) kan **zonder sleutel**.
- **Schrijven** gebruikt je persoonlijke API-sleutel via de header **`X-API-Key`**. Je vindt die in je profiel (`Account -> API-sleutel`) en kunt hem daar opnieuw genereren.
- Twee niveaus:
  - **eigen sleutel** (elke speler): je eigen voorspelling indienen en `/api/me`
  - **beheerderssleutel**: uitslagen, wedstrijden bijwerken en poules beheren

Foutcodes:
- `401` ontbrekende of ongeldige sleutel
- `403` geldige sleutel maar geen beheerder
- `404` niet gevonden
- `409` actie kan niet in de huidige status
- `422` validatiefout

---

## Lezen (publiek)

### `GET /api/standings`

Stand van een poule. Zonder code gebruik je de standaardpoule; een andere poule kan via het pad of `?pool=`.

```bash
curl https://trepiedi.online/api/standings
curl https://trepiedi.online/api/standings/kantoor
curl "https://trepiedi.online/api/standings?pool=kantoor"
```

```json
{
  "pool": { "name": "Tremani", "code": "algemeen" },
  "types": [
    { "key": "points", "emoji": "🟡", "label": "Algemeen", "field": "weightedTotal", "invertedMovement": false },
    { "key": "flat", "emoji": "🥞", "label": "Plattement", "field": "rawTotal", "invertedMovement": false },
    { "key": "score", "emoji": "⚽", "label": "Balletjestrui", "field": "scorePoints", "invertedMovement": false },
    { "key": "winners", "emoji": "🔮", "label": "Glazen bal", "field": "advanceCount", "invertedMovement": false },
    { "key": "lantern", "emoji": "🔴", "label": "Ronde lantaarn", "field": "lanternPoints", "invertedMovement": true },
    { "key": "inconsistent", "emoji": "🤔", "label": "Tegenstrijdig", "field": "inconsistentCount", "invertedMovement": false }
  ],
  "rankings": {
    "points": {
      "field": "weightedTotal",
      "entries": [
        { "rank": 1, "movement": 2, "player": "Anne", "slug": "anne", "value": 84 },
        { "rank": 2, "movement": -1, "player": "Bram", "slug": "bram", "value": 70 }
      ]
    },
    "flat":         { "field": "rawTotal",         "entries": [] },
    "score":        { "field": "scorePoints",     "entries": [] },
    "winners":      { "field": "advanceCount",     "entries": [] },
    "lantern":      { "field": "lanternPoints",    "entries": [] },
    "inconsistent": { "field": "inconsistentCount", "entries": [] }
  }
}
```

- `rankings`: per klassement-type (de sleutels uit `types`) een **eigen, al gesorteerde** lijst. Elk item heeft:
  - `rank`: de rang in dát klassement (tie-aware: gelijke `value` = gedeelde rang);
  - `value`: de waarde van dat klassement (het veld uit `types[].field`);
  - `movement`: positieverandering t.o.v. de vorige speeldag (`+` = gestegen, `-` = gezakt, `null` = nieuw of geen vergelijking).
- `points` telt met het rondegewicht mee (`weightedTotal`); `flat` (het "Plattement") is dezelfde stand maar ongewogen (`rawTotal`), waarin elke ronde 1× telt.
- Bij `lantern` en `inconsistent` staat de **meeste** strafpunten/tegenstrijdigheden op plek 1; een positieve `movement` betekent dus dat de speler richting die "winnaar"-plek is geschoven.
- `types`: de klassement-types met hun emoji, label, het bijbehorende `field` en `invertedMovement`. Die laatste is `true` als een hogere positie **ongunstig** is (alleen `lantern`): een positieve `movement` is dan "slechter" — een client kan op basis hiervan de kleur omdraaien (stijgen = rood i.p.v. groen).

### `GET /api/timeline`

Per **afgeronde** wedstrijd, per speler de gescoorde punten én de voorspelde uitslag. Bedoeld om de punten "per wedstrijd / per ronde / per speler" op te halen zonder zelf na te rekenen. Alleen afgeronde wedstrijden, dus voorspellingen lekken niet vóór de aftrap. Standaard de standaardpoule; een andere poule via het pad of `?pool=` (zelfde scoping als `/api/standings`).

```bash
curl https://trepiedi.online/api/timeline
curl https://trepiedi.online/api/timeline/kantoor
curl "https://trepiedi.online/api/timeline?pool=kantoor"
```

```json
{
  "pool": { "name": "Tremani", "code": "algemeen" },
  "matches": [
    {
      "matchId": 42,
      "round": "16e finales",
      "weight": 1,
      "home": "Nederland",
      "away": "Polen",
      "homeScore": 2,
      "awayScore": 1,
      "advancingSide": "home",
      "advancingTeam": "Nederland",
      "predictions": [
        {
          "player": "Anne",
          "slug": "anne",
          "homeScore": 2,
          "awayScore": 1,
          "advancingSide": "home",
          "points": 6.0,
          "rawPoints": 6,
          "scorePoints": 3,
          "winner": true
        }
      ]
    }
  ]
}
```

- elke deelnemer van de poule staat per wedstrijd in `predictions`; zonder voorspelling zijn `homeScore`/`awayScore`/`advancingSide` `null` en de punten 0
- `points`: gewogen punten als getal met decimaal (ruwe punten × rondegewicht); `rawPoints`: ongewogen geheel getal
- `scorePoints`: punten voor de uitslag (doelpunten thuis + uit + exacte-bonus, max 3); `winner`: of de doorgaande ploeg goed voorspeld is (+3)
- per ronde groeperen kan client-side op het veld `round`

### `GET /api/matches`

Alle wedstrijden.

```bash
curl https://trepiedi.online/api/matches
```

Elk item bevat onder meer:

```json
{
  "id": 42,
  "round": "16e finales",
  "kickoff": "2026-06-28T21:00:00+02:00",
  "home": "Nederland",
  "away": "Polen",
  "homeFlag": "nl",
  "awayFlag": "pl",
  "homeScore": 2,
  "awayScore": 1,
  "advancingTeam": "Nederland",
  "advancingFlag": "nl",
  "advancingSide": "home",
  "finished": true,
  "open": false,
  "resultViaExternalApi": false,
  "active": true,
  "locked": true,
  "predictable": false
}
```

- `homeFlag` / `awayFlag` / `advancingFlag`: [flag-icons](https://github.com/lipis/flag-icons)-code per ploeg, bijvoorbeeld `nl` of `gb-eng`, of `null` bij een placeholder zoals `2A`
- `resultViaExternalApi`: of de huidige uitslag via de externe API/MCP is binnengekomen (`true`) of handmatig via de backend (`false`); wordt `false` zodra de uitslag in de backend naar een afwijkende waarde wordt aangepast
- de respons bevat daarnaast een gededupliceerde `flags`-map `{ code: "<svg...>" }` met de SVG per voorkomende code, zodat de client de vlaggen zelf kan renderen
- `open`: uitslag is nog niet definitief
- `predictable`: je kunt nu nog een voorspelling indienen of aanpassen (`active` en niet `locked`)

### `GET /api/matches/{id}`

Een wedstrijd. Bevat `predictionCount` en na de aftrap ook een `predictions`-lijst.

```bash
curl https://trepiedi.online/api/matches/42
```

### `GET /api/rounds`

Ronden.

```bash
curl https://trepiedi.online/api/rounds
```

```json
{
  "rounds": [
    { "name": "16e finales", "sortOrder": 1, "weight": 1, "matchCount": 16 }
  ]
}
```

### `GET /api/flags/{code}`

Geeft de SVG van een vlagcode terug, met `Content-Type: image/svg+xml`.

```bash
curl https://trepiedi.online/api/flags/nl
```

---

## Met je eigen sleutel

### `GET /api/me`

```bash
curl -H "X-API-Key: JOUW_SLEUTEL" https://trepiedi.online/api/me
```

```json
{
  "displayName": "Anne",
  "slug": "anne",
  "admin": false,
  "pools": [
    { "name": "Tremani", "code": "algemeen", "default": true, "archived": false }
  ],
  "activePool": "algemeen"
}
```

### `POST /api/matches/{id}/prediction`

Je eigen voorspelling. Kan alleen als de wedstrijd `predictable` is. `advancingSide` is verplicht (`home` of `away`).

```bash
curl -X POST https://trepiedi.online/api/matches/42/prediction \
  -H "X-API-Key: JOUW_SLEUTEL" \
  -H "Content-Type: application/json" \
  -d '{"homeScore":2,"awayScore":1,"advancingSide":"home"}'
```

```json
{
  "match": 42,
  "prediction": { "homeScore": 2, "awayScore": 1, "advancingSide": "home" },
  "saved": true
}
```

---

## Met een beheerderssleutel

### `POST /api/matches/{id}/result`

Uitslag toevoegen of aanpassen. Alleen voor wedstrijden die nog **open** staan. `finished: true` maakt de uitslag definitief en vereist `advancingSide`.

```bash
curl -X POST https://trepiedi.online/api/matches/42/result \
  -H "X-API-Key: BEHEERDER_SLEUTEL" \
  -H "Content-Type: application/json" \
  -d '{"homeScore":2,"awayScore":1,"advancingSide":"home","finished":true}'
```

### `PATCH /api/matches/{id}`

Wedstrijd bijwerken. Je kunt ploegnamen invullen, activeren of deactiveren, of de aftrap aanpassen. Alle velden zijn optioneel.

```bash
curl -X PATCH https://trepiedi.online/api/matches/42 \
  -H "X-API-Key: BEHEERDER_SLEUTEL" \
  -H "Content-Type: application/json" \
  -d '{"home":"Nederland","away":"Brazilie","active":true,"kickoff":"2026-06-28T21:00:00"}'
```

### `GET /api/pools`

```bash
curl -H "X-API-Key: BEHEERDER_SLEUTEL" https://trepiedi.online/api/pools
```

### `POST /api/pools`

Poule aanmaken. `code` is optioneel; anders wordt die uit de naam gegenereerd. `default: true` maakt het de standaardpoule.

```bash
curl -X POST https://trepiedi.online/api/pools \
  -H "X-API-Key: BEHEERDER_SLEUTEL" \
  -H "Content-Type: application/json" \
  -d '{"name":"Vrienden"}'
```

```json
{ "name": "Vrienden", "code": "vrienden-7b2e", "default": false }
```

---

## MCP-server (`/mcp`)

`https://trepiedi.online/mcp` is een **MCP-server** (Streamable HTTP, JSON-RPC) die dezelfde functionaliteit als tools voor een AI-assistent aanbiedt, met dezelfde logica en dezelfde `X-API-Key`-authenticatie.

Koppelen, bijvoorbeeld in Claude Code:

```bash
claude mcp add --transport http trepiedi https://trepiedi.online/mcp --header "X-API-Key: jouw-sleutel"
```

Beschikbare tools:
- `get_standings`
- `get_timeline`
- `list_matches`
- `get_match`
- `get_rounds`
- `whoami`
- `submit_prediction`
- `set_match_result`
- `update_match`
- `list_pools`
- `create_pool`

Lezen kan zonder sleutel; schrijven met je eigen sleutel of een beheerderssleutel. Zie [`mcp/README.md`](../mcp/README.md) voor de hosted koppeling en een lokale stdio-variant.
