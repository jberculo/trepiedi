<?php

namespace App\Reference;

/**
 * Vaste lijst met deelnemende voetballanden voor de autocomplete en de vlaggetjes.
 *
 * Per land: de flag-icons-code (ISO 3166-1 alpha-2, of een sub-regio als gb-eng)
 * en de naam in het Nederlands en Engels. De namen die spelers/beheer invoeren
 * zijn vrije tekst; via codeForName() koppelen we zo'n naam terug aan een vlag.
 *
 * Het WK 2026 is nog niet volledig gekwalificeerd; dit is de gangbare set
 * voetbalnaties. Aanvullen kan door een regel toe te voegen.
 */
final class Countries
{
    /**
     * @var list<array{code: string, nl: string, en: string}>
     */
    private const LIST = [
        // Europa
        ['code' => 'nl', 'nl' => 'Nederland', 'en' => 'Netherlands'],
        ['code' => 'be', 'nl' => 'België', 'en' => 'Belgium'],
        ['code' => 'de', 'nl' => 'Duitsland', 'en' => 'Germany'],
        ['code' => 'fr', 'nl' => 'Frankrijk', 'en' => 'France'],
        ['code' => 'es', 'nl' => 'Spanje', 'en' => 'Spain'],
        ['code' => 'pt', 'nl' => 'Portugal', 'en' => 'Portugal'],
        ['code' => 'it', 'nl' => 'Italië', 'en' => 'Italy'],
        ['code' => 'gb-eng', 'nl' => 'Engeland', 'en' => 'England'],
        ['code' => 'gb-sct', 'nl' => 'Schotland', 'en' => 'Scotland'],
        ['code' => 'gb-wls', 'nl' => 'Wales', 'en' => 'Wales'],
        ['code' => 'gb-nir', 'nl' => 'Noord-Ierland', 'en' => 'Northern Ireland'],
        ['code' => 'ie', 'nl' => 'Ierland', 'en' => 'Ireland'],
        ['code' => 'hr', 'nl' => 'Kroatië', 'en' => 'Croatia'],
        ['code' => 'rs', 'nl' => 'Servië', 'en' => 'Serbia'],
        ['code' => 'ba', 'nl' => 'Bosnië en Herzegovina', 'en' => 'Bosnia and Herzegovina'],
        ['code' => 'ch', 'nl' => 'Zwitserland', 'en' => 'Switzerland'],
        ['code' => 'at', 'nl' => 'Oostenrijk', 'en' => 'Austria'],
        ['code' => 'pl', 'nl' => 'Polen', 'en' => 'Poland'],
        ['code' => 'dk', 'nl' => 'Denemarken', 'en' => 'Denmark'],
        ['code' => 'se', 'nl' => 'Zweden', 'en' => 'Sweden'],
        ['code' => 'no', 'nl' => 'Noorwegen', 'en' => 'Norway'],
        ['code' => 'cz', 'nl' => 'Tsjechië', 'en' => 'Czechia'],
        ['code' => 'ua', 'nl' => 'Oekraïne', 'en' => 'Ukraine'],
        ['code' => 'tr', 'nl' => 'Turkije', 'en' => 'Türkiye'],
        ['code' => 'gr', 'nl' => 'Griekenland', 'en' => 'Greece'],
        ['code' => 'hu', 'nl' => 'Hongarije', 'en' => 'Hungary'],
        ['code' => 'ro', 'nl' => 'Roemenië', 'en' => 'Romania'],
        ['code' => 'si', 'nl' => 'Slovenië', 'en' => 'Slovenia'],
        ['code' => 'sk', 'nl' => 'Slowakije', 'en' => 'Slovakia'],
        ['code' => 'is', 'nl' => 'IJsland', 'en' => 'Iceland'],
        ['code' => 'fi', 'nl' => 'Finland', 'en' => 'Finland'],
        // Zuid-Amerika
        ['code' => 'ar', 'nl' => 'Argentinië', 'en' => 'Argentina'],
        ['code' => 'br', 'nl' => 'Brazilië', 'en' => 'Brazil'],
        ['code' => 'uy', 'nl' => 'Uruguay', 'en' => 'Uruguay'],
        ['code' => 'co', 'nl' => 'Colombia', 'en' => 'Colombia'],
        ['code' => 'cl', 'nl' => 'Chili', 'en' => 'Chile'],
        ['code' => 'pe', 'nl' => 'Peru', 'en' => 'Peru'],
        ['code' => 'ec', 'nl' => 'Ecuador', 'en' => 'Ecuador'],
        ['code' => 'py', 'nl' => 'Paraguay', 'en' => 'Paraguay'],
        ['code' => 've', 'nl' => 'Venezuela', 'en' => 'Venezuela'],
        ['code' => 'bo', 'nl' => 'Bolivia', 'en' => 'Bolivia'],
        // Noord- en Midden-Amerika
        ['code' => 'us', 'nl' => 'Verenigde Staten', 'en' => 'United States'],
        ['code' => 'ca', 'nl' => 'Canada', 'en' => 'Canada'],
        ['code' => 'mx', 'nl' => 'Mexico', 'en' => 'Mexico'],
        ['code' => 'cr', 'nl' => 'Costa Rica', 'en' => 'Costa Rica'],
        ['code' => 'pa', 'nl' => 'Panama', 'en' => 'Panama'],
        ['code' => 'hn', 'nl' => 'Honduras', 'en' => 'Honduras'],
        ['code' => 'jm', 'nl' => 'Jamaica', 'en' => 'Jamaica'],
        // Azië en Oceanië
        ['code' => 'jp', 'nl' => 'Japan', 'en' => 'Japan'],
        ['code' => 'kr', 'nl' => 'Zuid-Korea', 'en' => 'South Korea'],
        ['code' => 'au', 'nl' => 'Australië', 'en' => 'Australia'],
        ['code' => 'sa', 'nl' => 'Saoedi-Arabië', 'en' => 'Saudi Arabia'],
        ['code' => 'ir', 'nl' => 'Iran', 'en' => 'Iran'],
        ['code' => 'qa', 'nl' => 'Qatar', 'en' => 'Qatar'],
        ['code' => 'iq', 'nl' => 'Irak', 'en' => 'Iraq'],
        ['code' => 'ae', 'nl' => 'Verenigde Arabische Emiraten', 'en' => 'United Arab Emirates'],
        ['code' => 'uz', 'nl' => 'Oezbekistan', 'en' => 'Uzbekistan'],
        ['code' => 'cn', 'nl' => 'China', 'en' => 'China'],
        ['code' => 'nz', 'nl' => 'Nieuw-Zeeland', 'en' => 'New Zealand'],
        // Afrika
        ['code' => 'ma', 'nl' => 'Marokko', 'en' => 'Morocco'],
        ['code' => 'tn', 'nl' => 'Tunesië', 'en' => 'Tunisia'],
        ['code' => 'dz', 'nl' => 'Algerije', 'en' => 'Algeria'],
        ['code' => 'eg', 'nl' => 'Egypte', 'en' => 'Egypt'],
        ['code' => 'sn', 'nl' => 'Senegal', 'en' => 'Senegal'],
        ['code' => 'ng', 'nl' => 'Nigeria', 'en' => 'Nigeria'],
        ['code' => 'gh', 'nl' => 'Ghana', 'en' => 'Ghana'],
        ['code' => 'cm', 'nl' => 'Kameroen', 'en' => 'Cameroon'],
        ['code' => 'ci', 'nl' => 'Ivoorkust', 'en' => 'Ivory Coast'],
        ['code' => 'ml', 'nl' => 'Mali', 'en' => 'Mali'],
        ['code' => 'za', 'nl' => 'Zuid-Afrika', 'en' => 'South Africa'],
        ['code' => 'cd', 'nl' => 'DR Congo', 'en' => 'DR Congo'],
        ['code' => 'cv', 'nl' => 'Kaapverdië', 'en' => 'Cape Verde'],
    ];

    /**
     * De flag-icons-code voor een (vrij ingevoerde) landnaam, of null als de naam
     * niet als deelnemend land wordt herkend.
     */
    public static function codeForName(?string $name): ?string
    {
        $needle = self::normalise((string) $name);
        if ($needle === '') {
            return null;
        }

        foreach (self::LIST as $country) {
            if (self::normalise($country['nl']) === $needle || self::normalise($country['en']) === $needle) {
                return $country['code'];
            }
        }

        return null;
    }

    /**
     * Zoek landen voor de autocomplete. Geeft maximaal 10 treffers terug,
     * gesorteerd op naam (in de gevraagde taal), met prefix-treffers eerst.
     *
     * @return list<array{name: string, code: string}>
     */
    public static function search(string $query, string $locale = 'nl'): array
    {
        $needle = self::normalise($query);
        if ($needle === '') {
            return [];
        }

        $key = $locale === 'en' ? 'en' : 'nl';

        $prefix = [];
        $contains = [];
        foreach (self::LIST as $country) {
            $name = $country[$key];
            $haystack = self::normalise($name);
            $pos = strpos($haystack, $needle);
            if ($pos === 0) {
                $prefix[] = ['name' => $name, 'code' => $country['code']];
            } elseif ($pos !== false) {
                $contains[] = ['name' => $name, 'code' => $country['code']];
            }
        }

        $sortByName = static fn (array $a, array $b): int => strcmp($a['name'], $b['name']);
        usort($prefix, $sortByName);
        usort($contains, $sortByName);

        return array_slice(array_merge($prefix, $contains), 0, 10);
    }

    /**
     * Normaliseer een naam voor vergelijking: trimmen, lowercase en diakritische
     * tekens strippen, zodat "Belgie", "belgië" en " BELGIË " gelijk matchen.
     */
    private static function normalise(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $value);

        return $ascii !== false ? strtolower($ascii) : $value;
    }
}
