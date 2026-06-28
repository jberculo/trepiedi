# Deployment

## Lokale ontwikkeling (huidige situatie)

Apache draait met `DocumentRoot c:/www`, dus de app is bereikbaar via
`http://localhost/trepiedi/public/`. `http://localhost/trepiedi` stuurt door naar
`/trepiedi/public/`.

Omdat de hele projectmap onder de DocumentRoot valt, beschermt de root-`.htaccess`:

- geen directory-listing (`Options -Indexes`);
- `.env*`, `composer.json/lock` en `*.yaml` worden geweigerd (403);
- de mappen `var`, `src`, `config`, `migrations`, `tests`, `bin`, `translations`
  en `vendor` zijn niet direct opvraagbaar (403).

PHP-bestanden worden uitgevoerd (geen broncode-lek), maar dit blijft een
dev-opstelling.

## Productie (aanbevolen)

Zet de DocumentRoot (of een vhost) op de **`public/`-map**. Dan:

- verdwijnt `/public` uit de URL (`https://poule.example/` i.p.v. `.../public/`);
- staat geen enkel niet-publiek bestand meer onder de webroot.

Voorbeeld vhost:

```apache
<VirtualHost *:80>
    ServerName poule.example
    DocumentRoot "/pad/naar/trepiedi/public"

    <Directory "/pad/naar/trepiedi/public">
        AllowOverride All
        Require all granted
        FallbackResource /index.php
    </Directory>
</VirtualHost>
```

Verder voor productie:

- Zet `APP_ENV=prod` en een sterke `APP_SECRET` in `.env.local` (of echte
  omgevingsvariabelen). Draai daarna `composer dump-env prod`.
- Gebruik een eigen database-account (niet `root`) met een wachtwoord.
- `composer install --no-dev --optimize-autoloader` en
  `php bin/console cache:clear --env=prod`.
- Schema bijwerken met `php bin/console doctrine:migrations:migrate`.
- Maak de beheerder aan met `php bin/console app:create-admin <email> <wachtwoord>`.

> Let op: draai **niet** `doctrine:fixtures:load` op productie — dat leegt de database.

## Tijdzone

De poule draait op Nederlandse tijd: ingevoerde aftraptijden zijn wandtijd in
`Europe/Amsterdam`. De applicatie pint die tijdzone zelf vast (`App\Util\AppTime`,
aangeroepen in `Kernel::__construct()`), zodat het gedrag niet afhangt van de
tijdzone van de server of `php.ini`.

Dit is belangrijk: zonder die pin interpreteert een server die in UTC draait een
ingevoerde "21:00" als 21:00 UTC (23:00 NL). Dan geldt een wedstrijd pas twee uur
te laat als "begonnen" en blijven de voorspellingen onterecht verborgen. Wijzig de
vastgepinde zone dus niet zonder reden; een afwijkende `date.timezone` in `php.ini`
heeft geen effect (de app overschrijft die bewust).
