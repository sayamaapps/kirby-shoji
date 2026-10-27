# Contributing

Bug reports, feature requests and pull requests are welcome. Support is provided on a best-effort basis.

## Before you start

Open an issue first for anything beyond a small fix, so the change can be discussed before work starts.

## Setup

```sh
git clone https://github.com/sayamaapps/kirby-shoji.git
cd kirby-shoji
composer install
```

To try the plugin on a site, link or copy the folder to `site/plugins/shoji/`.

## Checks

Run this before you open a pull request. It runs the Composer checks in the order of the CI analysis job, and the tests. CI runs the same checks on PHP 8.2 to 8.5.

```sh
composer ci
```

The steps, one by one:

```sh
composer test                     # PHPUnit
composer analyze:psalm            # Psalm
composer analyze:phpmd            # PHP Mess Detector
composer analyze:unused           # unused Composer packages
composer analyze:require-checker  # undeclared dependencies
composer analyze:platform         # platform requirements
composer normalize:check          # composer.json layout
```

Duplicated code is checked with phpcpd, installed globally (its Symfony Console dependency conflicts with the other tools). CI runs it as `phpcpd --fuzzy --exclude tests --exclude vendor .`.

Code style is checked with PHP-CS-Fixer and the Kirby rule set (`getkirby/php-cs`). The package is installed globally, because it needs PHP 8.3 or newer and this package supports PHP 8.2:

```sh
composer global require getkirby/php-cs:0.2.1
kirby-cs fix --dry-run --diff     # the global bin directory must be in PATH
```

## Conventions

- Tabs for indentation, LF line endings. `.editorconfig` lists the rest.
- Classes live in `src/`, one class per file. The class map in `autoload.php` lists them, and `tests/AutoloadTest.php` checks it.
- Keep public names stable. A rename gets a deprecation wrapper first, see the README section Deprecations.
- Commit messages follow Conventional Commits (`feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `style:`, `chore:`).

## Pull requests

Describe what changes and why. Add or update tests for a bug fix or a feature. Use the pull request template.
