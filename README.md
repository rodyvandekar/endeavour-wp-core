# Endeavour

Must-use plugin voor het Endeavour design system: post types, Elementor-widgets en de gecompileerde styles.

WordPress laadt alleen PHP-bestanden die direct in `wp-content/mu-plugins/` staan. De root van deze repository is die map. `endeavour.php` laadt de code in de map `endeavour/`.

Het versienummer staat in de header van `endeavour.php`.

## Installeren

Op een omgeving waar `wp-content/mu-plugins` nog niet bestaat:

```bash
git clone git@gitlab.pxlwidgets.com:internal/elementor-team/endeavour.git wp-content/mu-plugins
```

De plugin is daarna meteen actief. Een must-use plugin heeft geen activeer-knop.

## Updaten

```bash
cd wp-content/mu-plugins
git pull
```

## Styles

SCSS is de bron. Compileer vanuit de map `endeavour/` en commit de CSS mee:

```bash
npx --yes sass assets/scss/elementor-search.scss assets/css/elementor-search.css --no-source-map
```
