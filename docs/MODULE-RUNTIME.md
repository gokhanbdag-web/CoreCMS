# Module runtime contract

## Routes

A module may declare routes in module.json. Each route has a path, allowed HTTP methods and a handler path. Route handlers must resolve inside the module's Frontend directory and return a callable. The callable receives (PDO $db, array $params, array $request, array $module) and may return a string or null.

## Assets

Declare static assets as relative paths in the module-level assets array. Paths are relative to the module's assets directory. The runtime checks that the module is enabled, the path is declared, the file exists inside that directory and the extension is an allowed static type (CSS, JavaScript, SVG, common raster images or web fonts). PHP and other executable files are rejected.

The root rewrite rules deny direct access to module source, manifests, migrations, hooks, templates and views. Only the assets directory is web-accessible, and executable or hidden files inside it remain blocked.

## Module templates

Declare templates as a key-to-file map in module.json, for example templates.card = card.php. Files are resolved only inside the module's templates directory and must be PHP files. ModuleRuntime::renderTemplate(moduleId, key, data) renders a declared template for an enabled module.

## Themes

A module theme declares a directory containing layout.php. Optional theme.templates maps template keys to PHP files inside that theme directory. Optional theme.assets lists paths that must also be declared in the module-level assets array. ThemeManager validates and renders templates and resolves theme assets through the same module asset allowlist.

The public frontend renders the active theme's layout through ThemeManager rather than requiring the file directly. Theme CSS/JS assets are added to the page automatically; other assets can be resolved by the theme template through ThemeManager::assetUrl().

All module PHP files are trusted extension code. Install modules only from sources you trust.
