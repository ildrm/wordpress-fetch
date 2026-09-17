# Package verification

Artifact: `build/wordpress-fetch-1.0.0.zip`

Executed release checks:

- ZIP CRC/extraction test: passed.
- Main plugin file present at `wordpress-fetch/wordpress-fetch.php`.
- Admin bundle present at `wordpress-fetch/assets/dist/admin.js`.
- Dynamic block metadata, script dependency manifest, editor script, and renderer present.
- All packaged PHP files passed `php -l` after extraction.
- 59 production files packaged; tests, vendor, node_modules, VCS metadata, environment files, PHPStan config, and PHPCS config excluded.
- Initial package review detected and fixed an incorrect `/dist` destination and a missing `index.asset.php`; the artifact was rebuilt and re-extracted.

Activation, schema migration, authenticated REST, cron, and browser rendering require an actual WordPress/database/browser runtime and were not available in this repository. They are not represented as executed checks.
