# Endeavour WP Core

WordPress-plugin voor het Endeavour design system: post types, Elementor-widgets en de gecompileerde styles.

De plugin staat in `wp-content/plugins/endeavour/` en verschijnt in de gewone pluginlijst. Updates komen uit de publieke GitHub-releases en zijn te installeren via **Plugins → Bijwerken**.

## Installeren op een site

1. Download `endeavour.zip` van de laatste [release](https://github.com/rodyvandekar/endeavour-wp-core/releases).
2. Upload hem via **Plugins → Nieuwe plugin → Plugin uploaden**.
3. Activeer **Endeavour WP Core** eenmalig.

Er hoeft niets in `wp-config.php` en er is geen token nodig. WordPress downloadt de release zelf. De handtekening en de checksum controleren dat het bestand echt van deze repository komt.

Automatisch bijwerken zet je aan met **Automatische updates inschakelen** op de pluginrij.

## Lokaal ontwikkelen

Deze checkout is de git-repository zelf. Wijzigingen gaan via commit en push. Klik hier niet op **Bijwerken**: WordPress overschrijft dan de werkkopie met de release-zip.

Andere sites installeren alleen de zip en werken bij via het plugin-scherm.

## Een release maken

Het versienummer staat op twee plekken in `endeavour.php` en moet gelijk zijn: de header `Version` en de constante `ENDEAVOUR_VERSION`.

Eenmalig op GitHub: **Settings → Secrets and variables → Actions**, secret `ENDEAVOUR_SIGNING_KEY`. De waarde staat in `.signing/secret.key` op de machine waar de sleutel is aangemaakt. Die map staat in `.gitignore`. De sleutel verloopt niet.

Daarna:

```bash
git tag -a v1.4.0 -m "Korte release-notitie."
git push origin v1.4.0
```

GitHub Actions controleert of de tag bij de versie hoort, ondertekent de zip en publiceert `endeavour.zip` plus `manifest.json`. De tagtekst wordt de changelog.

Sites zien daarna een update zodra de tag hoger is dan de geïnstalleerde versie. WordPress downloadt de zip pas nadat de handtekening en de sha256-checksum kloppen.
