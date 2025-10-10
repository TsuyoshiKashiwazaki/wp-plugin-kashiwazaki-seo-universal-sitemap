# Repository Guidelines

## Project Structure & Module Organization
The plugin bootstrap lives in `kashiwazaki-seo-universal-sitemap.php` and registers hooks plus autoloads supporting classes. Functional logic is grouped inside `includes/` (`class-sitemap-generator.php`, `class-admin.php`, `class-post-meta.php`). Presentation assets reside in `assets/css/` and `assets/js/`, while shared admin markup is under `templates/admin-settings.php`. Generated sitemap files are written to the WordPress uploads directory at `wp-content/uploads/sitemaps/`; keep this path writable when testing.

## Build, Test, and Development Commands
There is no build pipeline; assets are committed pre-compiled. For local checks, run `php -l kashiwazaki-seo-universal-sitemap.php includes/*.php` to catch syntax issues. Use WP-CLI in your development stack to reload the plugin: `wp plugin deactivate kashiwazaki-seo-universal-sitemap && wp plugin activate kashiwazaki-seo-universal-sitemap`. After configuration changes, force regeneration with `wp cron event run ksus_regenerate_sitemaps`.

## Coding Style & Naming Conventions
Match the WordPress PHP coding standards: four-space indentation, lowercase `snake_case` for function names, and early returns to reduce nesting. Translate strings with `__()` / `_e()` so the text domain stays ready for localization. Keep admin handles and option keys prefixed with `ksus_` to avoid collisions. JavaScript follows ES5-compatible syntax; prefer vanilla DOM helpers over framework code.

## Testing Guidelines
Automated tests are not yet present, so rely on a WordPress test instance. Validate sitemap output by loading `/sitemap.xml` and subtype maps in the browser or with `curl`. When altering post meta handling, author fixtures in the dashboard and confirm regenerate hooks fire via the cron command above. If you add PHPUnit or wp-env coverage, mirror WordPress’ default `tests/phpunit` layout and name files `test-{feature}.php`.

## Commit & Pull Request Guidelines
Commits should be concise, present-tense commands (e.g., “Add news sitemap toggle”). Reference related issues with `Refs #123` in the body when applicable. Pull requests must state the problem, summarize the approach, list any migrations or cron updates, and include screenshots or XML snippets when touching admin UI or sitemap payloads. Mention manual verification steps so reviewers can reproduce your checks.

## Security & Configuration Tips
The sitemap generator writes XML files to uploads; confirm file permissions are restrictive (typically `755` directories and `644` files). Sanitize and escape all incoming admin settings with WordPress helpers like `sanitize_text_field()` and `esc_html()`. Avoid committing production API keys or site-specific configuration—use sample values and document overrides in the README instead.
