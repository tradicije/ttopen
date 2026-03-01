# OpenTT

**OpenTT** je slobodan i otvoren WordPress plugin namenjen vođenju, prikazu i arhiviranju stonoteniskih takmičenja: utakmica, klubova, igrača i statistika u jednom objedinjenom sistemu.

OpenTT je nastao iz realnih potreba kluba i zajednice, a ne kao komercijalni proizvod.
Cilj je jednostavan: **da alati, znanje i podaci ostanu u rukama zajednice.**

## Filozofija projekta

OpenTT se razvija po sledećim principima:

- Free & libre software: koristi, proučavaj, menjaj i deli.
- AGPL v3 licenca: ako OpenTT koristiš kao servis, izmene moraš deliti.
- Bez vendor lock-ina: podaci ostaju tvoji, u bazi i otvorenim formatima.
- Zajednica na prvom mestu: alat treba da služi klubovima, savezima i operaterima.

Ne postoji “Pro” verzija i nema skrivenih ograničenja funkcionalnosti.

## Zašto OpenTT postoji

Mnogi stariji sistemi za lige/rezultate imaju iste probleme:

- krhka CPT/ACF struktura za masovne podatke,
- komplikovan i nepouzdan admin unos,
- pad performansi kako rastu sezone i broj zapisa,
- zatvoreni sistemi koje je teško prilagoditi.

OpenTT to rešava direktno.
Ključna arhitekturna odluka:
**utakmice, partije i setovi se čuvaju u DB tabelama (ne kao CPT podaci).**

## Glavni ciljevi

- Zadržati postojeće frontend ponašanje i shortcode kompatibilnost.
- Premestiti teške match/game/set podatke iz CPT/ACF u namenske DB tabele.
- Omogućiti čist admin workflow za netehničke korisnike.
- Podržati i fresh instalacije i legacy migracije.

## Glavne mogućnosti

### Administracija (backend)

- Jedinstveni OpenTT admin:
  - Kontrolna tabla
  - Utakmice
  - Klubovi
  - Igrači
  - Takmičenja
  - Uvezi/Izvezi
  - Prilagođavanje
  - Podešavanja
- One-time onboarding za fresh instalacije.
- Vodičeni unos podataka za netehničke operatere.
- Batch unos partija/setova na strani izmene utakmice.
- Automatska dubl partija po formatu takmičenja (A/B).
- Upravljanje klubovima i igračima sa featured slikama i metapodacima.
- Pravila takmičenja sa logikom liga/sezona/savez/format.
- Live pretraga, filteri i sortiranje u admin listama.

### Frontend (shortcode sistem)

OpenTT zadržava kompatibilnost sa legacy shortcode-ovima uz novi DB model.

Primeri:

- `[prikaz_utakmica_grid]`
- `[prikaz_tabela]`
- `[lista_partija_nova]`
- `[h2h]`
- `[mvp]`
- `[izvestaj_utakmice]`
- `[snimak_utakmice]`
- `[prikaz_klubova]`
- `[prikaz_igraca]`
- `[info_kluba]`
- `[info_igraca]`
- `[top_igraci_lista]`
- `[statistika_igraca]`
- `[statistika_ekipe]`
- `[transferi]`
- `[info_takmicenja]`
- `[prikaz_takmicenja]`
- `[vesti_kluba]`, `[vesti_igraca]`

## Model podataka

OpenTT koristi namenske DB tabele za meč podatke:

- `wp_stkb_matches`
- `wp_stkb_games`
- `wp_stkb_sets`

Klubovi i igrači ostaju na CPT-ovima (`klub`, `igrac`) zbog kompatibilnosti i uredničkog workflow-a.

## Uvoz / Izvoz

OpenTT podržava selektivan uvoz/izvoz kroz JSON paket.

Sekcije:

- Takmičenja
- Klubovi
- Igrači
- Utakmice (DB)
- Partije (DB)
- Setovi (DB)

Tok:

1. Validacija uvoznog paketa.
2. Pregled sažetka i upozorenja.
3. Potvrda uvoza.

Dodatno:

- Prenos featured medija (slike klubova/igrača/logo takmičenja).
- Merge preview za potencijalne duplikate igrača pre potvrde uvoza.

## Routing i template-i

OpenTT podržava:

- Nove DB-based rute za utakmice.
- Legacy URL kompatibilnost.
- Prioritet theme override-a.
- Plugin fallback template-e kada ih tema nema.
- Kompatibilnost sa block i klasičnim PHP temama.

## Stilizacija i prilagođavanje

- Globalna vizuelna podešavanja (boje, radius, akcent).
- Napredni CSS override:
  - globalni
  - po shortcode-u
- Modularni frontend CSS u `assets/css/modules/`.
- Admin assets:
  - `assets/css/admin.css`
  - `assets/js/admin.js`

## Lokalizacija

- Promena jezika admin interfejsa je dostupna u **Podešavanjima**.
- Prevodni fajlovi su u `languages/admin-ui-<lang_code>.txt`.
- Format je: `english_reference = translation`.
- Novi jezički fajlovi se automatski detektuju i prikazuju u podešavanjima.
- Za novi prevod koristi `languages/admin-ui-template.example.txt` kao šablon.

## Licenca

**GNU Affero General Public License v3 (AGPL-3.0)**

Možeš slobodno da koristiš, menjaš i forkuješ OpenTT.
Ako ga pružaš kao servis (SaaS), važe AGPL obaveze.

Vidi: `LICENSE.txt`.

## Autor i zajednica

OpenTT razvija **Aleksa Dimitrijević**,
prvobitno za **STK Bubušinac** i **stkb.rs**.

Doprinosi su dobrodošli:

- prijave bagova,
- predlozi,
- pull request-ovi,
- fork-ovi koji poštuju licencu.

## Status projekta

Trenutna verzija: **1.0.0**.

## Napomena

Nazivi funkcija i internih identifikatora su namerno u nekim delovima ostavljeni kompatibilni sa starijim sistemom.
