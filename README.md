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

## Running the original app

You need Docker.

```bash
docker compose up -d --build
```

- **Site:** http://localhost:8056
- **Admin:** http://localhost:8056/admin, user `admin`, password `password`. This is the account from Croogo's sample data (see security finding 6).
- **Reset:** `docker compose down -v` wipes the database, and the next `up` starts from the sample data again.

The setup replaces Croogo's web installer, so every machine starts from the same state:

- **Database:** MySQL loads `app/config/schema/sql/croogo.sql` and `croogo_data.sql` on its first start.
- **Connection:** `docker/legacy/database.php` is mounted as `app/config/database.php`.
- **Settings:** the container copies `settings.yml.install` to `settings.yml`, as the installer's last screen does.

`app/config/core.php` keeps its shipped salt, because the seeded admin password hash depends on it. The passwords in `compose.yaml` are throwaway values for local use only.

The app runs on PHP 5.6 with Apache and mod_php, plus the old `mysql` extension, which CakePHP 1.3's default driver needs.

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

These come from reading the code. Each is confirmed or refuted against the running app in step 2.

1. **Weak password hashing.** Passwords are `sha1(salt + password)`: `Security::hash()` with CakePHP 1.3's default settings. That is fast and unsuited to passwords. **Fix:** `password_hash()` / `password_verify()`, with old hashes checked once more at login and then rehashed.
2. **Stored XSS from public comments into the admin.**
    - In `views/elements/comment.ctp`, a commenter's name is printed without escaping.
    - In `views/comments/admin_index.ctp`, the comment body goes into a `title` attribute with `'escape' => false`. A comment left by an anonymous visitor could run script in an administrator's browser.
    - The commenter's website becomes a link `href` without validation, which allows `javascript:` URLs.
    - Across the views, only 5 `h()` calls appear in 141 templates. Escaping depends on helpers, and the raw `echo` calls need a review.
3. **The file manager upload accepts any path and any file type.** `FilemanagerController::admin_upload()` takes the target folder from the URL and doesn't run the `_isEditable()` check that `admin_editfile()` uses. It also doesn't check the extension, so an admin session (or a forged request) could write a `.php` file into the web root.
4. **Attachment uploads aren't validated.**
    - `AttachmentsController::admin_add()` saves uploads into `webroot/uploads` with the client's file name and trusts the client's MIME type.
    - There is no extension allow-list.
    - `explode(...) > 0` compares an array with a number, which is a logic bug.
5. **The file manager can edit any file under `app/`,** PHP code included. It is a feature, but in practice it means code execution for anyone with an admin session. The modernized version should remove it or limit it to theme assets. `_isEditable()` also matches by prefix, so `/app` would match `/app_backup`.
6. **Insecure defaults:**
    - `debug` is 1 in `config/core.php`.
    - `Security.salt` and `Security.cipherSeed` ship as CakePHP's public defaults. The installer replaces them.
    - The installer creates `admin` with the password `password`.
7. **Old frontend libraries** bundled for the admin and TinyMCE have known issues. To be inventoried in step 2.

**Fixed in this project:** 1, 2, 3, 4 and 6. For 5, the decision is to remove the file editor instead of hardening it. 7 is documented and left for a later frontend step.

**Already in place:** CakePHP's Security component is active on every controller, which gives form tampering protection and CSRF tokens on forms. Whether destructive actions can still be triggered by GET links is checked in step 2. No `eval()`, `unserialize()` or raw `->query()` calls were found in Croogo's own code.

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
