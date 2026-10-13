# Endeavour

WordPress-plugin voor het Endeavour design system: post types, Elementor-widgets en de gecompileerde styles.

De plugin staat in `wp-content/plugins/endeavour/` en verschijnt in de gewone pluginlijst. Updates komen uit de releases van deze GitLab-repository en zijn te installeren via **Plugins → Bijwerken**.

## Installeren op een site

1. Download `endeavour.zip` van de laatste release, of pak die zip uit CI.
2. Upload hem via **Plugins → Nieuwe plugin → Plugin uploaden**, of pak hem uit naar `wp-content/plugins/endeavour/`.
3. Activeer **Endeavour** eenmalig.

Zet in `wp-config.php`, boven “That’s all, stop editing!”:

```php
define( 'ENDEAVOUR_UPDATE_TOKEN', 'glpat-…' );
```

Dat is een project access token met alleen `read_api`, of een deploy token als `gebruikersnaam:token`. Hetzelfde read-only token mag op alle sites. Zonder token laat WordPress geen update zien.

Automatisch bijwerken kan op twee manieren:

- Per site de schakelaar **Automatische updates inschakelen** op de pluginrij.
- Of in `wp-config.php`: `define( 'ENDEAVOUR_AUTO_UPDATE', true );`. Die constante wint van de schakelaar.

## Lokaal ontwikkelen

Deze checkout is de git-repository zelf. Wijzigingen gaan via commit en push. Klik hier niet op **Bijwerken**: WordPress overschrijft dan de werkkopie met de release-zip.

Andere sites installeren alleen de zip en werken bij via het plugin-scherm.

## Een release maken

Het versienummer staat op twee plekken in `endeavour.php` en moet gelijk zijn: de header `Version` en de constante `ENDEAVOUR_VERSION`.

Eenmalig in GitLab, bij deze repository:

1. **Settings → CI/CD → Variables**: `ENDEAVOUR_SIGNING_KEY`, masked en protected. De waarde staat in `.signing/secret.key` op de machine waar de sleutel is aangemaakt. Die map staat in `.gitignore`.
2. **Settings → CI/CD → Job token permissions**: dit project mag naar de package registry schrijven.

Daarna:

```bash
# versie in endeavour.php ophogen, committen en pushen
git tag -a v1.4.0 -m "Korte release-notitie."
git push origin v1.4.0
```

CI controleert of de tag bij de versie hoort, ondertekent de zip en publiceert `endeavour.zip` plus `manifest.json`. De tagtekst wordt de changelog.

Sites met het token zien daarna een update zodra de tag hoger is dan de geïnstalleerde versie. WordPress downloadt de zip pas nadat de handtekening en de sha256-checksum kloppen.
