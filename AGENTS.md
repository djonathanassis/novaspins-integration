# AGENTS.md

## Scope at a glance
- This is a single Laravel 11 service (PHP 8.3) for NovaSpins wallet callbacks; no monorepo/package split.
- Main runtime entrypoints are in `routes/api.php`: `POST /api/providers/novaspins/callback`, `POST /api/providers/novaspins/callback/replay`, `GET /api/players/{id}/wallet`, `GET /api/players/{id}/transactions`.

## Setup and runtime commands
- Preferred local runtime is Docker: `docker compose up -d --build`.
- Check container status: `docker compose ps`. Avoid `docker compose service status` unless the project defines a custom alias/script.
- After containers are up: `docker compose exec app composer install`.
- App bootstrap order matters on fresh envs: `docker compose exec app php artisan key:generate` then `docker compose exec app php artisan migrate --seed`.
- Service URL is `http://localhost:8080`; MySQL is exposed on `localhost:3307`.

## Verification commands (match CI behavior)
- Full tests: `docker compose exec app vendor/bin/phpunit`.
- Single test file: `docker compose exec app vendor/bin/phpunit tests/Feature/ProviderCallbackTest.php`.
- Single test method: `docker compose exec app vendor/bin/phpunit --filter test_win_callback_credits_wallet`.
- Style check: `docker compose exec app vendor/bin/php-cs-fixer fix --dry-run --diff`.
- CI runs PHPUnit and PHP-CS-Fixer in separate jobs (`.github/workflows/ci.yml`), so run both before finalizing.

## Request/auth flow you should not guess
- HMAC validation uses `X-Signature` over the **raw request body** with `NOVASPINS_HMAC_SECRET` (`app/Http/Middleware/VerifyProviderSignature.php`, `app/Services/HmacValidator.php`).
- `provider.callback` middleware group = signature verification + callback logging (`bootstrap/app.php`).
- `provider.callback.replay` intentionally skips signature verification and only logs callbacks (`bootstrap/app.php`, `routes/api.php`).

## Business logic hotspots
- Callback orchestration lives in `app/Http/Controllers/Api/ProviderCallbackController.php`.
- Money mutations are centralized in `app/Services/WalletService.php` and use `bcadd`/`bcsub`.
- Current idempotency behavior is asymmetric: `win` has duplicate guard by `provider_transaction_id` + type; `bet` and `rollback` do not.
- `rollback` logic reads `original_transaction_id` directly in the controller, while `CallbackRequest` marks it nullable; keep validation/controller expectations aligned if you change either.

## Data/test quirks that affect debugging

- **Historical migrations are immutable.** When schema changes are needed (e.g. `float` → `decimal`), create a new migration with `->change()` — never edit existing migration files. Editing an applied migration has no effect on the running database.
- Seeder creates deterministic demo data: player `external_id=player-001`, wallet `1000.00 BRL`, plus seeded transactions (`database/seeders/DatabaseSeeder.php`).
- PHPUnit uses SQLite in-memory (`phpunit.xml`), while app runtime uses MySQL (`.env`, `config/database.php`); DB behavior can differ.
- Transactions table indexes `provider_transaction_id` but does not enforce uniqueness (`database/migrations/2024_05_06_120200_create_transactions_table.php`).
- Docker PHP image pins `precision=4` and `serialize_precision=4`; float JSON rendering may differ if you run tests outside the container (`docker/app/Dockerfile`).

## OpenCode permission policy
- Keep real command permissions centralized in `opencode.json` whenever possible.
- Allow read-only diagnostics without repeated approval: `ls`, `find`, `grep`, `cat`, `sed -n`, `git status`, `git diff`, `docker compose ps`, local `curl` against `localhost`/`127.0.0.1`.
- `docker compose up -d --build` may be allowed for local environment bootstrap.
- Keep destructive or state-changing actions as approval-gated: file edits, `migrate:fresh`, `db:wipe`, `docker compose down -v`, `git add`, `git commit`, `git reset`, `git push`, dependency changes, and commands against non-local URLs.
- **Exception — novaspins-review-agent:** `git add` and `git commit` are set to `allow` in `opencode.json` intentionally. The Pre-commit Brief is the approval gate for commits in that agent, overriding the default above.
- `migrate:fresh --seed --no-interaction` may only be auto-allowed when guarded by checks confirming `APP_ENV=local`, local `DB_HOST`, and a non-production-like `DB_DATABASE` name.

## Agent execution behavior

- **Execute directly. Do not narrate.** If an instruction says "read AGENTS.md", output the file content — not "I will now read AGENTS.md".
- If you are about to write a sentence starting with "Vou", "Irei", "Iniciarei", or any planning phrase: stop and execute the command instead.
- After activating a skill, continue executing immediately — do not pause or wait for user confirmation.
- All steps between mandatory stops execute autonomously. Report results inline, not in advance.

## Repo-local agent tooling
- Repo includes OpenCode config in `opencode.json` and extra agent/subagent prompts under `.opencode/`; treat these as tooling config, not application runtime code.

## Bug hunt workflow (novaspins-review-agent)

Single-agent pipeline — no subagents. The agent performs all phases directly using embedded domain knowledge.

**Phases:**
- **Bootstrap:** `git status`, `docker compose ps`, `cat AGENTS.md`, diagnostic greps, `BUG_TS` definition.
- **Fase 1:** Validate known tickets against code. Read only — no edits.
- **Fase 2:** Second pass for bugs without tickets. Investigate all candidates — no artificial limit. Read only.
- **Fase 3:** Collect complete evidence per bug (curl, database-query, static analysis).
- **Fase 4:** Consolidated list — deduplicated, ordered by severity. CONFIRMADO / PROVÁVEL / PENDENTE / DESCARTADO.
- **🛑 PARADA — Confirmation Brief (1x):** awaits A/B/C/D before any edit.
- **Fase 5:** Fix one bug per cycle — hypothesis → test (FAIL) → spec → apply → PASS → linter → BUG_REPORT → Pre-commit Brief.
- **🛑 Pre-commit Brief (Nx):** diff + tests + linter before each commit. Awaits S/N/A.
- **Fase 6:** CALL_PREP.md — no code changes.

**Stop policy: 1 + N total stops** (N = approved bugs):
- 1x Confirmation Brief before any edit — awaits A/B/C/D.
- Nx Pre-commit Brief before each commit — awaits S/N/A.

After each commit: proceeds automatically to the next bug. After the last bug: goes to Fase 6.

**Output files:** `BUG_REPORT.md`, `CALL_PREP.md`, `.opencode/state/run-log.md`, `.opencode/state/pending.md`.

## Documentation Validation
Use the **context7** MCP tool to look up and validate Laravel 11, PHPUnit 11, and PHP 8.3 APIs before implementing or modifying any structure. This ensures fixes follow current framework conventions and avoid deprecated
patterns — especially important for `DB::transaction`, `lockForUpdate`,`hash_equals`, and bcmath functions that have subtle behavior differences between versions.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3
- laravel/framework (LARAVEL) - v11
- laravel/prompts (PROMPTS) - v0
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- phpunit/phpunit (PHPUNIT) - v11

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain — activate and continue executing immediately without pausing or narrating.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v11 rules ===

# Laravel 11

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Laravel 11 brought a new streamlined file structure which this project now uses.

## Laravel 11 Structure

- In Laravel 11, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- No app\Console\Kernel.php - use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Commands auto-register - files in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 11 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

## New Artisan Commands

- List Artisan commands using Boost's MCP tool, if available. New commands available in Laravel 11:
  - `php artisan make:enum`
  - `php artisan make:class`
  - `php artisan make:interface`

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

</laravel-boost-guidelines>