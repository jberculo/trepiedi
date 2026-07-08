# Waarom staan sterke voorspellers ook hoog in de ronde lantaarn?

Vraag: waarom scoren de mensen die hoog staan in de "positieve" klassementen (algemeen, balletjestrui, glazen bal) vaak ook hoog in de ronde lantaarn, terwijl je daar juist strafpunten krijgt voor slechte voorspellingen?

Kort antwoord: er spelen twee dingen. (1) De ronde lantaarn is een **ongewogen, niet-genormaliseerde som** van strafpunten, dus wie veel voorspelt verzamelt automatisch zowel veel plus- als veel strafpunten; over de hele groep ontstaat de correlatie daardoor via deelname (volume). (2) Onder de spelers die álles voorspeld hebben is volume gelijk, en dáár meet de lantaarn wél echte "met overtuiging fout" (omgekeerde uitslagen, verkeerde ploeg). Een topscorer als Juffrouw Jannie staat bovenaan de lantaarn omdat ze bold speelt: dezelfde scherpe, beslissende voorspellingen leveren haar de meeste exacte treffers én de meeste omgekeerde missers op. Het is dus geen fout in de berekening (die klopt precies) en ook niet "goede voorspellers voorspellen slecht", maar een gevolg van speelstijl en van een ruwe som die niet per voorspelling wordt genormaliseerd.

## Data

Bron: productie `GET /api/standings` + `/api/timeline`, poule "Tremani", 23 afgeronde wedstrijden (waarvan 3 gelijkspelen na verlenging, penalty's). De berekende lantaarn-opbouw matcht 1-op-1 met de waarden op de site.

| speler | # voorspeld | punten | balletjes | glazen bal | lantaarn | draw/rev/wrgAdv |
|---|---:|---:|---:|---:|---:|---|
| Emiel | 23 | 95 | 15 | 18 | 11 | 4/2/5 |
| Joost | 23 | 93 | 27 | 14 | 24 | 5/10/9 |
| Robert | 23 | 90 | 15 | 18 | 15 | 4/6/5 |
| Juffrouw Jannie | 23 | 89 | 31 | 14 | 24 | 3/12/9 |
| Justin Claudevert | 22 | 88 | 19 | 17 | 18 | 9/4/5 |
| Daphne United | 23 | 88 | 18 | 17 | 18 | 8/4/6 |
| Dinx | 23 | 87 | 23 | 15 | 19 | 7/4/8 |
| Lothar | 23 | 81 | 14 | 17 | 14 | 4/4/6 |
| Codinho | 22 | 78 | 14 | 17 | 18 | 9/4/5 |
| frankvm | 22 | 78 | 11 | 17 | 12 | 5/2/5 |
| TX11 | 23 | 76 | 13 | 14 | 24 | 5/10/9 |
| T.i.l.T. | 21 | 75 | 19 | 14 | 17 | 4/6/7 |
| Nemjit | 21 | 74 | 13 | 16 | 16 | 9/2/5 |
| RST | 8 | 28 | 10 | 6 | 4 | 2/0/2 |
| Tommy! | 8 | 25 | 6 | 5 | 7 | 2/2/3 |

Correlaties (Pearson, over deze 15 spelers):

- punten vs lantaarn: **+0.70**
- balletjestrui vs lantaarn: **+0.75**
- glazen bal vs lantaarn: **+0.47**
- aantal voorspellingen vs lantaarn: **+0.75**
- aantal voorspellingen vs punten: **+0.97**

## Verklaring

### 1. Deelname (volume) is de dominante oorzaak

Punten worden bijna volledig bepaald door hoeveel je voorspelt (+0.97 met het aantal voorspellingen). De lantaarn hangt daar ook sterk mee samen (+0.75). Beide klassementen zijn optelsommen zonder deling door het aantal voorspellingen, dus wie meedoet aan alle wedstrijden stapelt in beide gevallen punten op.

Het duidelijkst zichtbaar bij RST en Tommy!: zij voorspelden maar 8 van de 23 wedstrijden en staan onderaan in álle klassementen, ook in de lantaarn. Niet omdat ze goed voorspelden, maar omdat ze weinig voorspelden. De 13 actieve spelers voorspelden 21 tot 23 wedstrijden en hebben daardoor allemaal een fors absoluut lantaarn-totaal. Zo lopen "hoog in de positieve klassementen" en "hoog in de lantaarn" gelijk op: het zijn simpelweg dezelfde actieve deelnemers.

### 2. De lantaarn straft normale knock-outuitkomsten

Over 23 knock-outwedstrijden zit variatie ingebakken. De strafpunten verdelen zich als: verkeerde doorgaande ploeg 37%, omgekeerde uitslag 30%, gelijkspel-mismatch 33%.

- Zelfs een goede voorspeller heeft in een knock-out een flink deel van de doorgaande ploegen mis (upsets gebeuren). Bij 23 duels levert dat al snel 5 tot 9 strafpunten op puur uit "verkeerde ploeg doorgestuurd".
- Voor de balletjestrui moet je een concrete (beslissende) uitslag invullen. Wordt het toch een gelijkspel (beslist op penalty's), dan krijg je +1, ook als je de doorgaande ploeg wél goed had. Andersom levert een voorspeld gelijkspel dat beslissend eindigt ook +1 op.

Deze straffen treffen juist de mensen die volledige, concrete voorspellingen doen, en dat zijn dezelfde mensen die punten scoren.

### 3. Vaardigheid telt wél mee, maar wordt overstemd

De omgekeerde-uitslag-straf (+2) is het zwaarst en meet echt "met overtuiging fout". Dat verklaart waarom Joost en Juffrouw Jannie bovenaan de lantaarn staan (rev = 10 en 12): niet door volume, maar door relatief veel omgekeerde uitslagen. En omgekeerd is Emiel het tegenvoorbeeld dat de regel bevestigt: nummer 1 op punten (95) met de láágste lantaarn onder de actieve spelers (11). Vaardigheid maakt dus verschil, maar de volume-bodem zorgt dat vrijwel alle topspelers sowieso een hoog absoluut straftotaal hebben.

## Voorbeeld: Juffrouw Jannie (topscorer én hoogste lantaarn)

Juffrouw Jannie is het scherpste voorbeeld van het misverstand. Ze is #1 in de balletjestrui (31) en staat tegelijk bovenaan de lantaarn (24). Dat lijkt tegenstrijdig, maar haar wedstrijd-voor-wedstrijd-overzicht laat zien dat het klopt en logisch is.

- 23 wedstrijden voorspeld, waarvan **8 exact goed** (100% de score). Vandaar de hoogste balletjestrui.
- Maar ook **9 van de 23 doorgaande ploegen fout**, waarvan **6 volledig omgekeerd** (verkeerde ploeg én verkeerde scorerichting).

Die 6 omgekeerde uitslagen leveren elk +3 op (2 voor omgekeerd, 1 voor de verkeerde ploeg), samen 18 van haar 24:

| wedstrijd | zij voorspelde | het werd |
|---|---|---|
| België-Senegal | 1-2 (Senegal) | 3-2 (België) |
| VS-Bosnië | 1-2 (Bosnië) | 2-0 (VS) |
| Colombia-Ghana | 1-2 (Ghana) | 1-0 (Colombia) |
| Brazilië-Noorwegen | 2-1 (Brazilië) | 1-2 (Noorwegen) |
| Mexico-Engeland | 2-1 (Mexico) | 2-3 (Engeland) |
| VS-België | 2-1 (VS) | 1-4 (België) |

De overige 6 strafpunten: 2× "beslissend voorspeld maar het werd gelijk" plus verkeerde ploeg (Duitsland-Paraguay, Nederland-Marokko), 1× alleen verkeerde ploeg (Zuid-Afrika-Canada), 1× beslissend->gelijk (Australië-Egypte). Samen 18 + 4 + 1 + 1 = 24.

De les: Jannie is geen slechte voorspeller, ze is een **bold** voorspeller. Dezelfde durf om scherpe, beslissende uitslagen in te vullen levert haar de meeste exacte treffers (balletjestrui) én de meeste omgekeerde missers (lantaarn) op. Het zijn verschillende wedstrijden: de 8 die ze nagelde tegenover de 6 die volledig de andere kant op vielen. Wie voorzichtiger speelt (zoals Emiel) heeft minder van beide.

Belangrijk voor de interpretatie: onder de spelers die álles voorspeld hebben, is de lantaarn dus géén volume-maat maar een echte "durf/variantie"-maat. Volume verklaart alleen waarom de twee spelers met maar 8 voorspellingen onderaan álle klassementen bungelen.

## Conclusie en eventuele aanpassing

De ronde lantaarn meet nu grotendeels "wie doet er fanatiek mee en mist af en toe", niet "wie is de slechtste voorspeller". Dat is inherent aan een ongewogen som over alleen de wedstrijden die je invulde.

Wil je dat de lantaarn echt de *kwaliteit* (foutratio) meet in plaats van het volume, dan zijn dit de opties:

- Normaliseer per voorspelling: toon strafpunten per ingevulde wedstrijd (gemiddelde) in plaats van de ruwe som. Dan zakken de fanatiekelingen die vooral veel goed doen, en komen de echte missers boven.
- Of stel een drempel in (bijv. minimaal X voorspellingen) zodat weinig-voorspellers niet kunstmatig "goed" lijken.

Beide zijn bewuste ontwerpkeuzes; de huidige ruwe som is verdedigbaar als "engagement met een fout-tik", maar dan is de correlatie met de positieve klassementen logisch en geen bug.

---

Laatst gecontroleerd: 2026-07-07 (productiedata poule "Tremani", 23 afgeronde wedstrijden).
