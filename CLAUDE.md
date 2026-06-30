# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Wat dit is

Trepiedi is een voetbalpoule (knock-outfase, vanaf de 16e finales): deelnemers voorspellen uitslagen, een scoringsmodel kent punten toe en er zijn klassementen ("truien") per poule. Symfony 8.1 op PHP 8.4, Doctrine ORM, Twig, PostgreSQL (dev/prod). Daarnaast een REST-API en een MCP-server die dezelfde domeinlogica ontsluiten.

## Commando's

Symfony-console draait via `php bin/console …` (op Windows; `bin/console` is het script).

- Tests (PHPUnit 13): `php bin/phpunit` of `vendor/bin/phpunit`
- Eén testbestand: `php bin/phpunit tests/Scoring/ScoringServiceTest.php`
- Eén test: `php bin/phpunit --filter testNaam`
- Migraties draaien: `php bin/console doctrine:migrations:migrate`
- Migratie genereren uit entity-wijzigingen: `php bin/console make:migration`
- Fixtures laden (dev — **leegt de database**): `php bin/console doctrine:fixtures:load`
- Beheerder aanmaken: `php bin/console app:create-admin <email> <wachtwoord>`
- Poule aanmaken: `php bin/console app:create-pool …`
- Toernooi seeden: `php bin/console app:seed-tournament`
- Cache legen: `php bin/console cache:clear`
- Routes tonen: `php bin/console debug:router`

`failOnDeprecation`, `failOnNotice` en `failOnWarning` staan **aan** in `phpunit.dist.xml`: deprecations en notices laten de testsuite falen, dus die moeten echt opgelost worden.

### Database per omgeving (let op)

- **dev/prod**: PostgreSQL 16 (`DATABASE_URL` in `.env` / `.env.local`). Docker Compose start een Postgres-container.
- **test**: MariaDB/MySQL (`.env.test`), database krijgt automatisch het `_test`-suffix. De testschema-reset (`tests/DatabaseBootstrap`) heeft daarom MySQL-specifieke foreign-key-handling. Houd er rekening mee dat de twee omgevingen op verschillende DBMS draaien.
- **Draai niet twee testsuites tegelijk.** Alle functional tests delen één testdatabase en `DatabaseBootstrap::resetSchema()` dropt + herbouwt het schema in `setUp()`. Twee gelijktijdige `phpunit`-runs botsen daardoor op elkaars schema → schijnbare "errors" in de fixtures-setup (geen codefout). Laat een lopende run eerst afronden voordat je een nieuwe start.

## Architectuur

### Lagen en gedeelde domeinlogica

De kern is dat **web, REST-API en MCP dezelfde services delen**, zodat regels op één plek staan:

- `src/Controller/*` — web-UI (Twig). Admin-controllers onder `src/Controller/Admin/`.
- `src/Api/ReadApi.php` + `src/Api/WriteApi.php` — alle API-logica als platte arrays; gooien `ApiException` met een `ApiError`-enum. `ApiController` (REST) en `Mcp/ToolRegistry` roepen exact deze services aan.
- `src/Mcp/ToolRegistry.php` — definieert de MCP-tools (JSON Schema) én voert ze uit; delegeert naar `ReadApi`/`WriteApi`. `McpController` is het Streamable-HTTP/JSON-RPC-transport op `/mcp`.

Wanneer je gedrag van een schrijf-/leesactie wijzigt, raak je doorgaans `ReadApi`/`WriteApi` aan en werkt het meteen door in zowel REST als MCP. Pas dan `docs/api.md` en `mcp/README.md` bij.

### Scoring (`src/Scoring/`)

`ScoringService` is het hart van de puntentelling. Regels per wedstrijd (zie de class-docblock): 1 punt voor juiste doelpunten thuis, 1 voor uit, 1 bonus bij exacte uitslag, 3 voor de juiste doorgaande ploeg (max 6 ruw). Het **rondegewicht** (`Round::getWeight()`) is de vermenigvuldiger: gewogen punten = ruwe punten × gewicht.

Naast het gewone klassement berekent de service afgeleide klassementen ("truien"): `scorePoints` (balletjestrui), `winners` (glazen bal / doorgaande ploeg goed), `lanternPoints` (ronde lantaarn — strafpunten, zie `lanternPenalty()`) en `inconsistentCount` (tegenstrijdige voorspelling). De service is request-scoped en **memoïseert** alle dure lookups (zie de `*Cache`-velden); ga ervan uit dat data binnen één request read-only is.

`buildLeaderboard()` kent tie-aware rangen toe (gelijke `weightedTotal` ⇒ zelfde rang). `leaderboardWithMovement()` berekent positiewijziging t.o.v. de vorige speeldag via `positionMap()`.

### Pool-context (`src/Pool/`)

Alle klassementen zijn **gescoped op de actieve poule**. `PoolContext::getActivePool()` bepaalt welke poule de bezoeker ziet (gekozen poule → standaardpoule waar hij lid van is → eerste lidmaatschap → globale standaard voor uitgelogd/wees). `getMemberIds()` levert de user-id's waarop het klassement wordt beperkt. Een gearchiveerde poule telt niet mee.

### Tijdzone (belangrijk)

De app pint de tijdzone hard op `Europe/Amsterdam` via `App\Util\AppTime`, aangeroepen in `Kernel::__construct()` **vóór** er een datum geparset wordt. Ingevoerde aftraptijden zijn wandtijd in NL. Dit bepaalt wanneer een wedstrijd `isLocked()` (vanaf aftrap, voorspelling dicht) en `isAwaitingResult()` (aftrap + `RESULT_GRACE_HOURS`) is. Verander de vastgepinde zone niet zonder reden; zie `docs/deployment.md`.

### Wedstrijdstatus & uitslagen

`FootballMatch` modelleert knock-outwedstrijden: `homeScore`/`awayScore` zijn na verlenging zonder penalty's; `advancingSide` ('home'/'away') is wie doorgaat (mag na strafschoppen). `hasInconsistentResult()` / `MatchOutcome` detecteren een tegenstrijdige uitslag (score-winnaar ≠ doorgaande ploeg). `resultViaExternalApi` registreert of de uitslag via API/MCP binnenkwam (true) of handmatig in de backend (false; wordt false zodra de backend de waarde wijzigt).

### Auth

Twee sporen: sessie-login voor de web-UI (`LoginFormAuthenticator`) en `X-API-Key` voor API/MCP (`ApiKeyResolver` + `ApiTokenService`, gebruikt door `AuthorizesApiUser`). Lezen kan zonder sleutel; schrijven vereist een eigen sleutel, beheer-acties een beheerderssleutel.

### Overig

- `src/Reference/Countries.php` + `src/Flag/FlagProvider.php` — landnamen ⇄ flag-icons-codes en SVG-vlaggen (geserveerd via `/api/flags/{code}`).
- `src/Util/MatchOutcome.php` — gedeelde winnaar-/gelijkspel-/tegenstrijdigheidslogica; hergebruikt door scoring én entity.
- i18n via `translations/` en `LocaleSubscriber`.

## Deploy

Productie draait op `https://trepiedi.online` (server `jb-01`, 178.62.186.250). Er is **geen** CI/CD of deploy-script in de repo; de uitrol is een gedocumenteerde handmatige tar-deploy. De volledige procedure + een log per uitrol (met valkuilen) staat **buiten de repo** in `C:\www\AiCli\docs\trepiedi-online-deploy.md` — lees dat vóór een deploy.

Kort, voor een **code-only** wijziging (geen migratie/nieuwe classes):
1. `git archive --format=tar.gz -o deploy.tar.gz HEAD` → `pscp` naar `/tmp` op de server.
2. `cd /var/www/trepiedi.online && tar --exclude='public/uploads' -xzf /tmp/deploy.tar.gz`
3. `sudo rm -rf var/cache/prod && sudo -u www-data php8.4 bin/console cache:clear && sudo chown -R www-data:www-data var`

Bij een migratie: extra `sudo -u www-data php8.4 bin/console doctrine:migrations:migrate`. Bij nieuwe/verwijderde classes: `composer dump-autoload --no-dev --optimize` en verwijderde bestanden expliciet `rm`-en op de server (tar voegt alleen toe).

## Conventies

- PHP volgt **PSR-12**: 4 spaties, geen decoratieve uitlijning van `=>`/`=`, opening brace van methodes/classes op eigen regel, van control-structures op dezelfde regel.
- Commitboodschap: één korte regel die start met `ADD:`/`CHG:`/`DEL:`/`FIX:`, lege regel, dan het storynummer. Geen co-author-regels.
- Domein-/commentaartaal is Nederlands; volg die toon in nieuwe code en docs.
- Bij refactors: dek de uitkomst af met (nieuwe) tests en let op code-duplicatie.
- API/MCP-gedrag gewijzigd? Werk `docs/api.md` én `mcp/README.md` bij.
