# Endeavour

Must-use plugin voor het Endeavour design system: post types, Elementor-widgets en de gecompileerde styles.

WordPress laadt alleen PHP-bestanden die direct in `wp-content/mu-plugins/` staan. De root van deze repository is die map. `endeavour.php` laadt de code in de map `endeavour/`.

De plugin verschijnt niet bij de gewone plugins. Een nieuwe release installeer je via **Extra → Endeavour**.

Het versienummer staat op twee plekken en moet gelijk zijn: de header van `endeavour.php` en `ENDEAVOUR_VERSION` in `endeavour/endeavour.php`.

## Installeren

Op een omgeving waar `wp-content/mu-plugins` nog niet bestaat:

```bash
git clone git@gitlab.pxlwidgets.com:internal/elementor-team/endeavour.git wp-content/mu-plugins
```

Zet daarna in `wp-config.php` een token met alleen leesrechten. Een project access token met scope `read_api`:

```php
define( 'ENDEAVOUR_UPDATE_TOKEN', 'glpat-...' );
```

Of een deploy token, geschreven als gebruiker en token:

```php
define( 'ENDEAVOUR_UPDATE_TOKEN', 'gitlab+deploy-token-1:gldt-...' );
```

De plugin is meteen actief. Een must-use plugin heeft geen activeer-knop.

Om nieuwe releases zonder knop te installeren:

```php
define( 'ENDEAVOUR_AUTO_UPDATE', true );
```

## Een release maken

1. Zet de base64-inhoud van `.signing/secret.key` in GitLab als variabele `ENDEAVOUR_SIGNING_KEY`. Maskeer hem en beperk hem tot protected tags. Het bestand blijft buiten git.
2. Zet onder **Settings → CI/CD → Job token permissions** dat deze pipeline naar de package registry mag schrijven.
3. Hoog het versienummer op beide plekken op, bijvoorbeeld naar `1.3.0`.
4. Commit en push naar `main`.
5. Zet een annotated tag die exact die versie is, en push de tag:

```bash
git tag -a v1.3.0 -m "Korte changelog van deze release."
git push origin v1.3.0
```

De pipeline bouwt `endeavour.zip` en een ondertekend `manifest.json`, en hangt die aan de GitLab-release. Elke site ziet de release daarna onder **Extra → Endeavour**. De dagelijkse controle doet hetzelfde.

De handtekening wordt gecontroleerd vóór er een bestand wordt vervangen. Lukt de nieuwe code niet, dan zet de site de vorige installatie terug. Die reservekopie kan ook met de hand via hetzelfde scherm.

## Styles

SCSS is de bron. Compileer vanuit de map `endeavour/` en commit de CSS mee:

```bash
npx --yes sass assets/scss/elementor-search.scss assets/css/elementor-search.css --no-source-map
```
