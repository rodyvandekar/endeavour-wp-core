# Endeavour WP Core

WordPress-plugin voor het Endeavour design system: post types, Elementor-widgets en de gecompileerde styles.

De plugin staat in `wp-content/plugins/endeavour-wp-core/` en verschijnt in de gewone pluginlijst. Updates komen uit de publieke GitHub-releases en zijn te installeren via **Plugins → Bijwerken**.

## Installeren op een site

1. Download `endeavour.zip` van de laatste [release](https://github.com/rodyvandekar/endeavour-wp-core/releases).
2. Upload hem via **Plugins → Nieuwe plugin → Plugin uploaden**.
3. Activeer **Endeavour WP Core** eenmalig.

Er hoeft niets in `wp-config.php` en er is geen token nodig. WordPress downloadt de release zelf. De handtekening en de checksum controleren dat het bestand echt van deze repository komt.

WordPress installeert een release niet zelf. De update verschijnt in de pluginlijst, en Plugins → Bijwerken of een beheertool zoals ManageWP installeert hem.

## Eenmalige hernoeming op een site die nog `endeavour/` gebruikt

Vanaf 1.5.0 heet de map `endeavour-wp-core`. Updates houden de map die er al staat, dus een site verandert niet vanzelf. Hernoem hem één keer, met een backup en de site op een kopie:

```bash
mv wp-content/plugins/endeavour wp-content/plugins/endeavour-wp-core
```

Pas daarna de actieve plugin aan, anders ziet WordPress hem als ontbrekend. Lees de lijst met `wp option get active_plugins --format=json`, vervang `endeavour/endeavour.php` door `endeavour-wp-core/endeavour.php` en schrijf de lijst terug met `wp option update active_plugins '<json>' --format=json`.

## Lokaal ontwikkelen

Deze checkout is de git-repository zelf. Wijzigingen gaan via commit en push. Klik hier niet op **Bijwerken**: WordPress verwijdert de hele map en zet de release-zip ervoor in de plaats. Daarmee verdwijnen `.git`, `.signing/` en `build/`.

Andere sites installeren alleen de zip en werken bij via het plugin-scherm.

## Een release maken

Het versienummer staat op twee plekken in `endeavour.php` en moet gelijk zijn: de header `Version` en de constante `ENDEAVOUR_VERSION`.

Eenmalig op GitHub: **Settings → Secrets and variables → Actions**, secret `ENDEAVOUR_SIGNING_KEY`. De waarde staat in `.signing/secret.key`. Bewaar die sleutel buiten de plugin-map, bijvoorbeeld in `~/.config/endeavour/`, want een update op een site wist de hele map. De sleutel verloopt niet.

Daarna:

```bash
git tag -a v1.5.0 -m "Korte release-notitie."
git push origin v1.5.0
```

Lokaal testen zonder GitHub: `CI_COMMIT_TAG=v1.5.0 ENDEAVOUR_SIGNING_KEY=… php bin/build-release.php` schrijft `build/endeavour.zip` en `build/manifest.json`.

GitHub Actions controleert of de tag bij de versie hoort, ondertekent de zip en publiceert `endeavour.zip` plus `manifest.json`. De tagtekst wordt de changelog.

Sites zien daarna een update zodra de tag hoger is dan de geïnstalleerde versie. WordPress downloadt de zip pas nadat de handtekening en de sha256-checksum kloppen.
