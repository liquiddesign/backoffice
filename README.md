# liquiddesign/backoffice

Administrace pro weby Liquid Design postavené na Nette a StORM. Nástupce `liquiddesign/admin` —
navržený tak, aby v jedné aplikaci běžel **vedle** starého adminu a agendy šlo převádět po jedné.

> Stav: **0.x, rozpracováno.** API se mění bez ohledu na zpětnou kompatibilitu až do 1.0.
> První nasazení: PSE (pse.cz). Návrh vzhledu: artefakt „Nová administrace PSE“.

## Co obsahuje

- **Shell** — layout, navigace, drobečky, Ctrl K, notifikace, štítek prostředí, přihlášení s 2FA
  nad stávajícími tabulkami `admin_*`
- **Deklarativní agendy** — seznam a formulář popsané jednou třídou nad StORM repozitářem
- **Most** — staré presentery `liquiddesign/admin` vykreslené v novém shellu
- **Asistent** — AI asistent nad znalostmi a nástroji, které dodá projekt
- **Pomoc** — požadavek z administrace do Liquid Design (e-mail, později Takt)
- **Odběratelé API** — evidence klíčů, IP a limitů pro veřejné API projektu
- **Knihovna souborů**

Databáze: MySQL 8, vlastní tabulky jen s prefixem `adm_`, stávající tabulky projektu se nemění.

## Struktura

```
src/          kód balíku (namespace Backoffice\)
assets/       zdroje Vue/TS/SCSS → dist/ (zkompilované, součást balíku)
migrations/   tabulky adm_*
playground/   ukázková Nette aplikace pro vývoj a testy bez konkrétního projektu
docs/         architektura a rozhodnutí
tests/
```

Podrobnosti: [docs/architektura.md](docs/architektura.md).

## Instalace

```bash
composer require liquiddesign/backoffice:dev-main
```

```neon
extensions:
	backoffice: Backoffice\DI\BackofficeExtension
```

## Vývoj

```bash
composer install
composer check-code   # phpcs + phpstan
composer tester
```

## Licence

MIT
