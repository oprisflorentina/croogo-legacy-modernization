# Croogo 1.3 legacy modernization

This repository takes a 2012-era PHP application, the Croogo 1.3 CMS on CakePHP 1.3, and modernizes it step by step:

1. **Run it as it was:** the original code in Docker, plus tests that capture its behavior.
2. **Move it to PHP 8.3** without changing what it does.
3. **Port parts of it to Symfony 7** with the strangler fig pattern.

Every step is its own commit, and the tests written in the first stage are the proof that behavior didn't change.

Croogo and CakePHP are open source under the MIT license. Credit for the original code goes to their authors: Croogo by Fahad Ibnay Heylaal, Rachman Chavik and contributors, and CakePHP by the Cake Software Foundation. Their license files are kept unchanged in `app/LICENSE.txt` and `cake/LICENSE.txt`.

## Starting point

| | |
| --- | --- |
| Application | [Croogo](https://github.com/croogo/croogo) 1.3.6, tag `1.3.6`, commit `1ac533a` |
| Framework | [CakePHP](https://github.com/cakephp/cakephp) 1.3.21, tag `1.3.21`, commit `fa5eac7`. It's the last 1.3 release (October 2015); Croogo doesn't require a specific 1.3.x |
| PHP | Croogo asks for PHP 5.2 or later. Here it runs on PHP 5.6, because Docker 29 can no longer pull the official PHP 5.3-5.5 images (old schema 1 manifests) |
| Database | MySQL. MySQL 5.5 is used here, the version from the same period |
| Web server | Apache with mod_php. Friendly URLs depend on `.htaccess` and mod_rewrite |
| Not included | The `locale` git submodule (translations). The app runs in English without it |

## Repository layout

The first commit is the standard CakePHP 1.3 layout, with Croogo as the application:

- `cake/`, `plugins/`, `vendors/`, `index.php`, `.htaccess`: CakePHP 1.3.21 as released.
- `app/`: Croogo 1.3.6 as released. It replaces the default `app/` skeleton that ships with CakePHP.

Two things differ from upstream:

- **The root `.gitignore`:** CakePHP's own `.gitignore` was dropped, because it excludes `/app/config`, `/plugins` and `/vendors`, which belong to the application here.
- **Placeholder folders:** the empty placeholder files in `app/tmp/`, `app/webroot/uploads/` and `app/views/themed/` are tracked even though `app/.gitignore` ignores them, so a fresh clone has the folders the app needs at runtime.

## Running the app

You need Docker.

```bash
docker compose up -d --build
```

- **Site:** http://localhost:8083 (PHP 8.3)
- **Admin:** http://localhost:8083/admin, user `admin`, password `password`. This is the account from Croogo's sample data (see security finding 6).
- **Email:** http://localhost:8025 shows every email the app sends, caught by [Mailpit](https://mailpit.axllent.org/) instead of being delivered.
- **Reset:** `docker compose down -v` wipes the database, and the next `up` starts from the sample data again.

**To see the original app on PHP 5.6,** check out the tag `last-php56` and run the same command. It's the last commit that runs on both versions, with the PHP 5.6 container on http://localhost:8056.

The setup replaces Croogo's web installer, so every machine starts from the same state:

- **Database:** MySQL loads `app/config/schema/sql/croogo.sql` and `croogo_data.sql` on its first start.
- **Connection:** `docker/php83/database.php` is mounted as `app/config/database.php`.
- **Settings:** the container copies `settings.yml.install` to `settings.yml`, as the installer's last screen does.

`app/config/core.php` keeps its shipped salt, because the seeded admin password hash depends on it. The passwords in `compose.yaml` are throwaway values for local use only.

**About the PHP 5.6 image** (at `last-php56`): the Debian 9 package archive behind it is signed with keys that have since expired. Rather than turning off apt's signature check, its Dockerfile builds zlib, libjpeg and libpng from their official source releases, downloaded over HTTPS and pinned by SHA-256.

PHP's `mail()` hands messages to Mailpit. That matters for more than convenience: the sample contact form only confirms a message *after* the email is sent, and it doesn't save the message anywhere (`message_archive` is off). Without a mail server, the form fails silently and the message is lost.

## Characterization tests

`tests/characterization/` holds 18 tests that record how the app behaves today. They run in their own PHP 8.3 container (PHPUnit 12 and Symfony BrowserKit) and only talk to the site over HTTP, like a browser. They don't load any of the app's code, so the same tests run unchanged against PHP 5.6, against PHP 8.3, and later against Symfony.

```bash
docker compose run --rm tests composer install   # once
docker compose run --rm tests                    # run them
```

Besides what each test checks, **every page any test loads fails the test if it shows a PHP error**. The app runs in debug mode, so CakePHP prints warnings and notices into the HTML, and a page can show the right content while failing underneath. Forms are submitted exactly as a browser sends them, including CakePHP's hidden companion inputs.

| File | What it pins down |
| --- | --- |
| `AdminPagesTest` | Every admin screen reachable from the menu (about 240 pages) renders without PHP errors. Links that change data through a GET request are never followed. |
| `PublicPagesTest` | Home page and main menu, a blog post, a static page, search, the RSS feed, and unknown addresses (which answer **200** with an error page, not 404) |
| `AdminAuthTest` | Redirect to login, rejected passwords, a working admin login, and the lockout after 5 failed attempts |
| `ContentFlowsTest` | An admin creates, edits and deletes a blog post; a visitor comments; a visitor's contact message reaches the site owner by email (checked through Mailpit's API) |
| `KnownIssuesTest` | Behavior that is wrong but real: the `javascript:` website link, double-encoded comment names, PHP running from an attachment upload, and the rejected File Manager forms. Each fix in step 4 flips one of these on purpose. |

**Built to run again and again:**
- Every run uses fresh random values.
- Test posts aren't promoted, and they're deleted at the end.
- Uploaded probe files are removed.

The suite passed three runs in a row on the same database. Writing it surfaced two behaviors the code review missed:
- **The account lockout:** using `admin` for the wrong-password test locked the other tests out.
- **The silent contact form:** described above.

**Checked the other way too:** changing one confirmation message in `contacts_controller.php` made exactly one test fail. Reintroducing a fixed PHP 8 warning made the error check fail the tests that load that page.

## Upgrade to PHP 8.3 (in progress)

**Steps 1-5 below:** the same working tree ran in two containers, PHP 5.6 on :8056 and PHP 8.3 on :8083, sharing one database. After every change, both ran the full test suite: the PHP 8.3 run showed progress, and the PHP 5.6 run proved nothing changed behavior along the way. The tag `last-php56` marks the end of that phase, with all 18 tests passing on both versions.

**Done so far,** one commit each:

1. **PHP 4-era syntax:** `=& new`, `$str{0}`, `each()`, `create_function()` (now closures), magic quotes, PHP 4 constructors.
2. **Reserved class names:** `Object` became `CakeObject` and `String` became `CakeText`, the names CakePHP itself moved to in 2.x.
3. **Static calls to instance methods**, about 1,650 call sites. A tokenizer pass marked 149 methods `static`. Three relied on PHP 5 letting the caller's `$this` leak into a static call and were reworked by hand.
4. **Method signatures:** about 30 overrides that PHP 8 rejects as incompatible with their parent. `tools/check-declarations.php` loads every class in its own process to list them all in one pass. The MSSQL, Oracle, Postgres and SQLite drivers were removed rather than ported untested.
5. **Runtime differences**, found by the tests and the admin crawl:
    - PHP 8.1's new `full_path` upload key broke the form security check.
    - PHP 7's built-in `Error` class made CakePHP skip loading its own error handler.
    - `@` no longer silences warnings under CakePHP's error handler.
    - The bundled Spyc YAML parser crashed on the Plugins page.
    - Smaller items: `implode()` argument order, `count()` on `false`, an invalid regex range.

6. **Rector** (`tools/quality/rector.php`) applied the PHP 8.3 rule sets across 225 files: short arrays, explicit visibility, `str_contains()`, `??` and more. It skipped seven rules that would have changed behavior here. The sharpest: `RandomFunctionRector` would have replaced the seeded `rand()` in `Security::cipher()` with `random_int()`, which can't be seeded, and cookie encryption would have broken silently, untouched by any test. From this commit on, the code needs PHP 8, and the PHP 5.6 container is retired.

**Still to do in this step:** PHPStan with a baseline, the password migration and the security fixes. Then the `php83` tag.

## Structure

| Area | Files |
| --- | --- |
| Controllers | 17 (nodes, users, comments, contacts, attachments, filemanager, menus, links, blocks, regions, types, terms, vocabularies, roles, settings, languages, messages) |
| Components | 3 (`croogo`, `akismet`, `recaptcha`) |
| Models | 17, plus 7 behaviors (`cached`, `croogo_translate`, `encoder`, `meta`, `ordered`, `params`, `url`) |
| View helpers | 5 (`custom`, `filemanager`, `image`, `layout`, `recaptcha`) |
| Plugins | 6 (`acl`, `example`, `extensions`, `install`, `tinymce`, `translate`) |
| Views | 141 `.ctp` templates |
| Own tests | 22 SimpleTest cases with 23 fixtures. SimpleTest itself isn't bundled |
| Size | Croogo: 178 PHP files, about 25,400 lines. CakePHP core: 452 PHP files, about 53,600 lines in `cake/libs` |

**Content model:** everything is a *node* (pages, blog posts, attachments). Nodes have a *type*, are classified with *vocabularies* and *terms*, and carry extra fields through *meta*. Menus, links, blocks and regions build the layout. Access control uses CakePHP's ACL (`acos`, `aros`, `aros_acos`).

## Database

There are 22 tables, defined in `config/schema/schema.php`, with SQL versions in `config/schema/sql/`. The web installer (`plugins/install`) creates them and loads sample data.

`acos`, `aros`, `aros_acos`, `blocks`, `comments`, `contacts`, `languages`, `links`, `menus`, `messages`, `meta`, `nodes`, `nodes_taxonomies`, `regions`, `roles`, `settings`, `taxonomies`, `terms`, `types`, `types_vocabularies`, `users`, `vocabularies`

## What breaks on PHP 8.3

**Syntax check.** Every file was checked with `php -l` on PHP 8.3; nothing was executed. 141 of 886 files don't parse:

| Area | Files that don't parse |
| --- | --- |
| CakePHP core, runtime | 37 |
| CakePHP core, console | 12 |
| CakePHP core, its own tests | 86 |
| Croogo | 6 |

`php -l` stops at the first error in each file, so these counts are a floor. On PHP 5.6 the only failure is `overloadable_php4.php`, a PHP 4 compatibility file that PHP 5 never loads.

**Pattern scan.** These are counts of occurrences outside the test folders:

| Problem | PHP version | Core | Croogo |
| --- | --- | ---: | ---: |
| Calling a non-static method statically (`Configure::read()`, `ClassRegistry::init()`, `Router::url()` and others). None of the core's methods are declared `static` | Error since 8.0 | 1,286 | 372 |
| `=& new` (assigning `new` by reference) | removed in 7.0 | 77 | 10 |
| Return by reference (`function &getInstance()`) | still works, but usually tied to the static-call problem | 46 | 1 |
| A class named `Object`, which every core class extends | reserved since 7.2 | 43 | 6 |
| A class named `String` (`String::uuid()` and similar) | reserved since 7.0 | 27 | 2 |
| `mysql_*` functions: the `mysql` driver Croogo is configured with | removed in 7.0 | 19 | 0 |
| `each()` | removed in 8.0 | 10 | 0 |
| Curly-brace string offsets (`$str{0}`) | removed in 8.0 | 8 | 0 |
| PHP 4 constructors (`Object`, `Router`, `CakeRoute`, `FileLog`, `ShellDispatcher`) | no longer constructors since 8.0 | 5 | 0 |
| `create_function()` | removed in 8.0 | 3 | 0 |
| `get_magic_quotes_*()` | removed in 8.0 | 2 | 0 |
| `mcrypt_encrypt()` (reCAPTCHA Mailhide helper) | removed in 7.2 | 0 | 1 |
| `join($array, $glue)`, arguments reversed (`FilemanagerController::_isDeletable`) | TypeError since 8.0 | not scanned | 1 |
| `utf8_encode()` / `utf8_decode()` | deprecated in 8.2 | 1 | 0 |
| `var $property` declarations and dynamic properties | dynamic properties deprecated in 8.2 | 713 | 37 |

**What this means:**

- **Static calls are the largest job.** They aren't syntax errors, so `php -l` doesn't catch them. They break at runtime, everywhere.
- **The `Object` and `String` renames touch every class in the core.**
- **The database driver has to change.** The core already ships a `mysqli` driver (`dbo_mysqli.php`), which is the shortest path. A PDO driver is the cleaner one.
- **Fixed alongside the upgrade:** `=& new`, `each()`, string offsets, `create_function()` and the PHP 4 constructors are mechanical changes. Rector can apply part of them, and the rest are fixed by hand.

## Security findings

These come from reading the code, then checking it against the running app (step 2). Each finding says what the check showed.

1. **Weak password hashing.** Passwords are `sha1(salt + password)`: `Security::hash()` with CakePHP 1.3's default settings. That is fast and unsuited to passwords. **Fix:** `password_hash()` / `password_verify()`, with old hashes checked once more at login and then rehashed.

    *Confirmed:* the seeded admin hash `c054b15…` is exactly `sha1(default salt + "password")`.
2. **XSS through the commenter's website.** The comment form accepts any value as the website, and the public page turns it into the link on the commenter's name. A visitor who clicks that name runs the script.

    *Confirmed:* a comment posted without logging in, with the website `javascript:alert(1)`, was published immediately and rendered as `<a href="javascript:alert(1)">`.

    *Corrected after testing:* the code reading also suspected the comment name and body. They turned out not to be exploitable, because `CommentsController` runs `htmlspecialchars()` on them **before saving**. That's escaping at the wrong layer, though:
    - The database holds HTML entities instead of the text people typed.
    - Pages that escape again show them double-encoded, for example `&amp;lt;i&amp;gt;`.
    - Any other way of writing comments would skip the protection.

    The fix escapes on output and stores raw text.
3. **The file manager upload accepts any path and any file type.** `FilemanagerController::admin_upload()` takes the target folder from the URL and doesn't run the `_isEditable()` check that `admin_editfile()` uses. It also doesn't check the extension, so an admin session could write a `.php` file anywhere the web server can write, including the web root.

    *Confirmed in the code, but not reachable with this CakePHP version.* Every File Manager form (upload, new file, new folder, rename) is rejected with "Security Error", in a real browser too. The cause:
    - **CakePHP 1.3.18** (April 2014) started tying each form's security token to the URL it posts to.
    - **Croogo's File Manager** builds its form URLs as absolute addresses with a `?path=` query (`http://host/admin/filemanager/upload?path=…`).
    - **`FormHelper::_lastAction()`** prepends the base URL to them a second time. The token ends up hashed with a broken path, so it never matches the request.

    The hole is still in Croogo's code; it only stays closed by accident, and an older 1.3 core opens it again. A small upgrade with a security fix silently broke a feature, which is exactly what characterization tests are meant to catch.
4. **Attachment uploads aren't validated.** `AttachmentsController::admin_add()` saves uploads into `webroot/uploads` with the client's file name. It trusts the MIME type the client sends and has no extension allow-list. It also contains a logic bug: `explode(...) > 0` compares an array with a number.

    *Confirmed:* a file `probe.php` uploaded as an attachment with the type `image/png` was saved to `/uploads/probe.php`, and requesting it **ran the PHP code**. Anyone with an admin session (or who steals one) can run code on the server.
5. **The file manager can edit any file under `app/`,** PHP code included. It is a feature, but in practice it means code execution for anyone with an admin session. The modernized version should remove it or limit it to theme assets. `_isEditable()` also matches by prefix, so `/app` would match `/app_backup`.
6. **Insecure defaults:**
    - `debug` is 1 in `config/core.php`.
    - `Security.salt` and `Security.cipherSeed` ship as CakePHP's public defaults. The installer replaces them.
    - The installer creates `admin` with the password `password`.
7. **Old frontend libraries** bundled for the admin and TinyMCE have known issues. To be inventoried in step 2.
8. **Anyone can lock out any account.** After 5 failed logins in 5 minutes, a username is blocked for everyone, including its owner with the right password (`UsersController::beforeFilter`). Knowing that the admin is called `admin` is enough to keep them out.

    *Found while writing the tests:* the wrong-password test locked out the admin account the other tests use.

**Fixed in this project:**
- **Fixed in code:** 1, 2, 4, 6 and 8. For 8, failed attempts are counted per username *and* IP address, so an attacker only locks out themselves.
- **Removed:** findings 3 and 5 are resolved by removing the File Manager completely, rather than repairing its forms and then hardening them. A CMS doesn't need a web-based editor for server files, and media uploads stay available through Attachments, where finding 4 gets fixed.
- **Documented only:** 7 is left for a later frontend step.

**Already in place:**
- **Form protection:** CakePHP's Security component is active on every controller, which gives form tampering protection and CSRF tokens on forms.
- **Delete links:** admin deletes are GET links, but each carries the session's token (`/admin/nodes/delete/2/token:…`), so a forged link from another site doesn't work.
- **No risky calls:** no `eval()`, `unserialize()` or raw `->query()` calls were found in Croogo's own code.

## Modernization plan

Each step ends with a commit and, where noted, a tag.

| Step | What | Tag |
| --- | --- | --- |
| 1 | Import the original code with this analysis | `legacy-original` |
| 2 | Run it in Docker: PHP 5.6, Apache, MySQL 5.5 | |
| 3 | Characterization tests: HTTP tests (PHPUnit) in a separate PHP 8.3 container that don't depend on the app's code | |
| 4 | PHP 8.3: the core first, then Croogo, Rector, PHPStan with a baseline, password migration, security fixes | `php83` |
| 5 | CI on GitHub Actions: tests and PHPStan on every push | |
| 6 | Apache with mod_php replaced by nginx + PHP-FPM | |
| 7 | One area moved to Symfony 7 behind the same nginx, on the same database (strangler fig) | |

## How this was built

Florentina Opris directed the work: she chose the project and the approach, set the rules, reviewed every change, and ran and tested the app. Claude Code (Anthropic) wrote most of the code and the scans behind this analysis under her direction. The CakePHP 1.3 core changes for PHP 8.3 are written fresh for this repository.
