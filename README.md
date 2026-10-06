# montazladowarki.pl

Katalog instalatorów ładowarek do samochodów elektrycznych na WordPressie. Klient wpisuje adres → widzi firmy, które deklarują dojazd w to miejsce → wysyła jedno zapytanie do kilku firm. Instalatorzy zakładają konto, ustawiają obszar działania (miasto + promień, jak w OLX) i odbierają zapytania.

Repo zawiera dwie rzeczy:

| Katalog | Co to jest |
|---|---|
| `plugin/montazladowarki-core` | Wtyczka: dane, wyszukiwanie po odległości, panel instalatora, zapytania ofertowe, strony SEO miast, promowanie |
| `theme/montazladowarki` | Lekki motyw (strona główna, nagłówek, stopka, blog/poradnik) |

Wtyczka działa też z innym motywem — jej szablony mają własne style, a motyw może je nadpisać (katalog `mlc/` w motywie).

![Strona główna](docs/home-1366.png)

## Instalacja

1. Wgraj `montazladowarki-core` do `wp-content/plugins/` i `montazladowarki` do `wp-content/themes/` (albo spakuj każdy katalog do ZIP i wgraj przez panel).
2. **Ustawienia → Bezpośrednie odnośniki**: ustaw „Nazwa wpisu” (`/%postname%/`).
3. Aktywuj wtyczkę. Przy aktywacji:
   - tworzą się tabele `wp_mlc_places` (43 989 miejscowości z PRNG ze współrzędnymi) i `wp_mlc_areas`,
   - tworzą się strony: **Szukaj** (`/szukaj/`), **Dla instalatorów** (`/dla-instalatorow/`), **Panel instalatora** (`/panel-instalatora/`), **Regulamin**,
   - dodaje się rola „Instalator” i domyślne usługi.
4. Aktywuj motyw.
5. **Instalatorzy → Ustawienia**: nazwa serwisu, e-mail powiadomień, moderacja, kafelki mapy.
6. Uzupełnij **Regulamin** i **Politykę prywatności** (zapytania przekazują dane klienta firmom — potrzebna podstawa prawna RODO).
7. Skonfiguruj wysyłkę maili (np. WP Mail SMTP) — bez tego zapytania i powiadomienia mogą nie dochodzić.

Wymagania: WordPress 6.4+, PHP 8.0+, MySQL 5.7+/MariaDB 10.3+.

## Jak to działa

**Wyszukiwanie.** Autouzupełnianie miejscowości działa lokalnie (bez płatnego API, także bez polskich znaków: „lodz” → Łódź). Pełny adres („ul. Puławska 5, Piaseczno”) jest geokodowany przez OpenStreetMap Nominatim z cache. Jest też przycisk „użyj mojej lokalizacji”. Firma pojawia się w wynikach, gdy punkt wyszukiwania leży w promieniu któregoś z jej obszarów. Kolejność: promowane → lokalne → zweryfikowane → najbliższe. Firmy „Cała Polska” są na końcu.

**Panel instalatora** (`/panel-instalatora/`): logo, opis, dane, usługi, do 10 obszarów działania, lista otrzymanych zapytań. Nowe firmy czekają na akceptację (można wyłączyć). Instalator nie widzi wp-admin.

**Zapytania ofertowe.** Klient zaznacza do 5 firm i wysyła jeden formularz. Każda firma dostaje maila z `Reply-To` klienta, klient dostaje potwierdzenie. Zapytania są zapisywane (menu Instalatorzy → Zapytania) i usuwane po 12 miesiącach. Ochrona: nonce, honeypot, minimalny czas wypełnienia, limit na IP.

**Promowanie.** W edycji firmy (panel admina) ustawiasz datę „Promowane do”. Firma trafia na górę list, dostaje znacznik i wyróżnioną pinezkę. Na razie ręcznie — patrz plan rozwoju.

## SEO

- **Strony miast** `/montaz-ladowarki/{miasto}/` dla 954 miast: unikalne H1, opis z liczbą firm, statystyki (cena od, firmy z SEP), lista firm z mapą, FAQ z lokalnymi danymi, linki do pobliskich miast.
- **Strony województw** `/montaz-ladowarki/woj-{nazwa}/` i **hub** `/montaz-ladowarki/`.
- Miasta bez firm mają `noindex, follow` (unikamy cienkich treści) i automatycznie przechodzą do indeksu, gdy pojawi się firma.
- **Schema.org**: `Electrician` (z `areaServed` jako `GeoCircle`) na profilach, `FAQPage` + `ItemList` na miastach, `BreadcrumbList` wszędzie, `WebSite` + `SearchAction` na stronie głównej.
- **Mapa witryny** `/mlc-sitemap-miasta.xml` (tylko indeksowalne miasta), dopisana do `robots.txt`, a przy Yoast/Rank Math także do ich indeksu map.
- Działa z Yoast SEO i Rank Math (tytuły, opisy i canonical stron wirtualnych są przekazywane przez ich filtry). Bez wtyczki SEO motyw sam wstawia meta description, OG i canonical.
- Wyniki wyszukiwania i panel mają `noindex`.
- Wydajność: Leaflet lokalnie (bez CDN), skrypty z `defer`, mapa ładowana dopiero, gdy wjedzie w ekran, brak jQuery na froncie.

Liczba firm na miasto jest przeliczana w tle po każdej zmianie (WP-Cron) lub ręcznie: **Instalatorzy → Ustawienia → Przelicz strony miast**.

## Ważne przed startem produkcyjnym

- **Kafelki mapy**: domyślnie publiczne serwery OpenStreetMap, które nie są przeznaczone do dużego ruchu. Przy realnym ruchu ustaw dostawcę (MapTiler, Stadia Maps, Thunderforest) w ustawieniach.
- **Nominatim** ma limit 1 zapytanie/s — wtyczka go pilnuje i cache'uje wyniki. Przy dużej skali warto przejść na płatny geokoder; autouzupełnianie miejscowości nie zależy od żadnego API.
- **WP-Cron**: przy małym ruchu warto podpiąć systemowy cron (`wp cron event run --due-now`).
- Dane miejscowości: Państwowy Rejestr Nazw Geograficznych (stan 2021), opracowanie [jjbartek/polskie-miejscowosci](https://github.com/jjbartek/polskie-miejscowosci).

## Plan rozwoju

1. **Płatne promowanie** — pakiety (7/30/90 dni) z płatnością Przelewy24/Stripe lub WooCommerce; pole `_mlc_promoted_until` jest gotowe, wystarczy je ustawiać po opłaceniu.
2. **Płatne leady** — limit darmowych zapytań na miesiąc, kolejne płatne.
3. **Opinie klientów** po zrealizowanym zleceniu (link w mailu do klienta) + `AggregateRating` w schema.
4. **Galeria realizacji** w profilu firmy.
5. **Poradnik** (blog) — artykuły o kosztach, dotacjach, doborze ładowarki, linkujące do stron miast.
6. Import startowej bazy firm (CSV) i zaproszenia mailowe do przejęcia profilu.

## Struktura wtyczki

```
montazladowarki-core/
├─ montazladowarki-core.php     bootstrap
├─ includes/
│  ├─ class-install.php         tabele, role, strony, import miejscowości
│  ├─ class-post-types.php      CPT instalator / zapytanie, usługi, obszary
│  ├─ class-geo.php             miejscowości, autouzupełnianie, geokodowanie
│  ├─ class-search.php          wyszukiwanie po promieniu, liczniki miast
│  ├─ class-rest.php            /wp-json/mlc/v1/places, /search
│  ├─ class-dashboard.php       rejestracja i panel instalatora
│  ├─ class-leads.php           zapytania ofertowe
│  ├─ class-seo.php             strony miast, meta, schema, sitemap
│  ├─ class-frontend.php        zasoby, shortcode'y, szablon profilu
│  └─ class-admin.php           metaboxy, kolumny, ustawienia
├─ templates/                   szablony (nadpisywalne w motywie: mlc/*.php)
├─ assets/                      CSS, JS (bez zależności), Leaflet
└─ data/places.csv.gz           miejscowości
```

Shortcode'y: `[mlc_search_form]`, `[mlc_search_results]`, `[mlc_join]`, `[mlc_dashboard]`.
