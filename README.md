# Croogo 1.3 legacy modernization

[![CI](https://github.com/oprisflorentina/croogo-legacy-modernization/actions/workflows/ci.yml/badge.svg)](https://github.com/oprisflorentina/croogo-legacy-modernization/actions/workflows/ci.yml)

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

- **Site:** http://localhost:8083 (PHP 8.3 as PHP-FPM, behind nginx)
- **Admin:** http://localhost:8083/admin, user `admin`, password `password`. This is the account from Croogo's sample data (see security finding 6).
- **Email:** http://localhost:8025 shows every email the app sends, caught by [Mailpit](https://mailpit.axllent.org/) instead of being delivered.
- **Reset:** `docker compose down -v` wipes the database, and the next `up` starts from the sample data again.

**To see the original app on PHP 5.6,** check out the tag `last-php56` and run the same command. It's the last commit that runs on both versions, with the PHP 5.6 container on http://localhost:8056.

The setup replaces Croogo's web installer, so every machine starts from the same state:

- **Database:** MySQL loads `app/config/schema/sql/croogo.sql` and `croogo_data.sql` on its first start.
- **Connection:** `docker/php83/database.php` is mounted as `app/config/database.php`.
- **Settings:** the container copies `settings.yml.install` to `settings.yml`, as the installer's last screen does.

**Configuration comes from environment variables** (security finding 6). Without them the app runs in production mode. `compose.yaml` sets throwaway values for local use:

| Variable | What it does | In `compose.yaml` |
| --- | --- | --- |
| `CROOGO_DEBUG` | CakePHP's debug level. 0, production mode, when it isn't set. | `1`: the tests read PHP warnings from the pages |
| `CROOGO_SECURITY_SALT` | The site's own `Security.salt` | a local value |
| `CROOGO_CIPHER_SEED` | The site's own `Security.cipherSeed` (digits) | a local value |
| `CROOGO_LEGACY_SALT` | The salt old SHA1 password hashes were made with, needed only after the salt changes | CakePHP's published salt, which the sample data used |

An installed site in production mode refuses to run with CakePHP's published salt or seed. It answers 500 and logs which variables to set. The other passwords in `compose.yaml` are also throwaway values for local use only.

**About the PHP 5.6 image** (at `last-php56`): the Debian 9 package archive behind it is signed with keys that have since expired. Rather than turning off apt's signature check, its Dockerfile builds zlib, libjpeg and libpng from their official source releases, downloaded over HTTPS and pinned by SHA-256.

PHP's `mail()` hands messages to Mailpit. That matters for more than convenience: the sample contact form only confirms a message *after* the email is sent, and it doesn't save the message anywhere (`message_archive` is off). Without a mail server, the form fails silently and the message is lost.

## Characterization tests

`tests/characterization/` holds 41 tests that record how the app behaves today. They run in their own PHP 8.3 container (PHPUnit 12 and Symfony BrowserKit) and talk to the site over HTTP, like a browser. They don't load any of the app's code, so the same tests run unchanged against PHP 5.6, against PHP 8.3, and later against Symfony.

A few tests also use the database directly. They don't depend on which app serves the site, because the tables stay the same:
- The password tests read the stored hash, which is what they check.
- One comment test changes a saved row, to stand for data stored before a fix.
- The attachment tests read the stored file name and type.

**A second visitor:** the `relay` service in `compose.yaml` ([socat](http://www.dest-unreach.org/socat/)) passes TCP connections to the app unchanged. A request sent through it reaches the app from the relay's IP address instead of the tests container's. The login lockout tests use it to stand for an attacker on another address. It adds no header, so the test doesn't depend on anything the app would have to trust.

```bash
docker compose run --rm tests composer install   # once
docker compose run --rm tests                    # run them
```

Besides what each test checks, **every page any test loads fails the test if it shows a PHP error**. The app runs in debug mode (`CROOGO_DEBUG=1` in `compose.yaml`), so CakePHP prints warnings and notices into the HTML, and a page can show the right content while failing underneath. Forms are submitted exactly as a browser sends them, including CakePHP's hidden companion inputs.

| File | What it pins down |
| --- | --- |
| `AdminPagesTest` | Every admin screen reachable from the menu (219 pages) renders without PHP errors. Links that change data through a GET request are never followed. |
| `AdminUsersTest` | An admin creates a user, who can then log in, and can't give a new user a password shorter than 6 characters. Added when PHPStan showed that user validation was broken on PHP 8. |
| `PublicPagesTest` | Home page and main menu, a blog post, a static page, search, the RSS feed, and unknown addresses (which answer **200** with an error page, not 404) |
| `AdminAuthTest` | Redirect to login, rejected passwords, a working admin login, and the lockout after 5 failed attempts. The lockout counts per username and address: failures through the relay don't lock the owner out, forged address headers don't get around it, and every spelling of a username counts against the same account. |
| `ContentFlowsTest` | An admin creates, edits and deletes a blog post; a visitor comments; an attachment whose stored name has no extension is listed as `file` |
| `ContactFormTest` | The public contact form, which is the first area to move to Symfony. A message reaches the site owner by email, checked through Mailpit's API: the subject, the sender, and every line of the body. Missing fields are named, and nothing is sent until they pass. The email shows what was typed, and a name that looks like an address can't change the sender. With archiving on, the message is stored as typed and escaped in the admin. |
| `LegacyLayoutTest` | `/legacy-layout`, Croogo's layout around two placeholders, which Symfony fills in. It renders for the page it's asked for: the main menu marks that page, and a block limited to some pages appears only for them. Anything but a plain path on this site renders for the home page. |
| `KnownIssuesTest` | Behavior that was wrong but real when the tests were written. Each fix in step 4 flipped one of these on purpose, and all of them are flipped now: a `javascript:` website is refused, and one already stored is not shown as a link. Comment text is stored as typed and escaped when shown, no longer double-encoded. A `.php` attachment is refused, and other attachments get a safe name and the type the server detects. The File Manager is gone: no menu link, no pages, no ACL records. CakePHP's web test runner `/test.php` is gone too, and so is CakePHP 1.2's `js/vendors.php`. |
| `PasswordHashingTest` | An old SHA1 hash is replaced at the first successful login and left alone after a failed one. The admin reset and "forgot password" flows accept an old hash and store a new one. Both resets refuse a password shorter than 6 characters and keep the old hash. The `password` column has room for 255 characters. Added with the password migration in step 4. |

**Built to run again and again:**
- Every run uses fresh random values.
- Test posts aren't promoted, and they're deleted at the end.
- Uploaded probe files are removed.

The suite passed three runs in a row on the same database. Writing it surfaced two behaviors the code review missed:
- **The account lockout:** using `admin` for the wrong-password test locked the other tests out.
- **The silent contact form:** described above.

**Checked the other way too:** changing one confirmation message in `contacts_controller.php` made exactly one test fail. Reintroducing a fixed PHP 8 warning made the error check fail the tests that load that page.

## Upgrade to PHP 8.3

| | Before (`legacy-original`) | After (`php83`) |
| --- | --- | --- |
| PHP | 5.2 or later (5.6 here). On PHP 8.3, 141 files don't parse: 37 in the CakePHP runtime, 12 in its console, 86 in its own tests, 6 in Croogo | 8.3. The CakePHP runtime, its console and Croogo's code all parse, and `tools/check-declarations.php` loads every class without a fatal error. Only test code that never runs still fails: CakePHP's own 86 tests and one of Croogo's SimpleTest cases |
| CakePHP core | 1.3.21 as released: static calls to instance methods, classes named `Object` and `String`, PHP 4 constructors, removed functions | 1.3.21 adapted to PHP 8.3, with the changes written fresh for this repository |
| Database driver | `mysql_*`, removed in PHP 7 | `mysqli`, which the core already shipped. The other drivers were removed rather than ported untested |
| Passwords | `sha1(salt + password)` | `password_hash()` (bcrypt). Old hashes are replaced at the next login, so nobody has to reset |
| Configuration | Debug on. CakePHP's published salt and seed in `core.php` | Production mode unless `CROOGO_DEBUG` is set. Secrets come from environment variables, and production refuses the published ones |
| Security findings | 8, found by reading the code and checked against the running app | 5 fixed in code (1, 2, 4, 6, 8), 2 resolved by removing the File Manager (3, 5), 1 documented only (7) |
| Tests | Croogo's 22 SimpleTest cases, which can't run: SimpleTest isn't bundled | 27 characterization tests over HTTP, including a crawl of 219 admin pages that fails on any PHP error |
| Static analysis | None | PHPStan level 5. The 2012 code's findings are kept in a baseline, which went from 3,696 to 3,524. New and changed code has to pass |
| Automated refactoring | None | Rector's PHP 8.3 rules on 225 files, with 7 rules skipped on purpose |
| Running it | Croogo's web installer, on a server you set up yourself | `docker compose up`: the app, MySQL and Mailpit, plus containers for the tests and the quality tools |
| Not changed in this step | | MySQL 5.5, Apache with mod_php (nginx and PHP-FPM come in step 6), the database schema |

**Steps 1-5 below:** the same working tree ran in two containers, PHP 5.6 on :8056 and PHP 8.3 on :8083, sharing one database. After every change, both ran the full test suite: the PHP 8.3 run showed progress, and the PHP 5.6 run proved nothing changed behavior along the way. The tag `last-php56` marks the end of that phase, with all 18 tests passing on both versions.

**What was done,** one commit each:

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

7. **PHPStan** (`tools/quality/phpstan.neon`). Every level 0 finding was read before anything went into a baseline, and they included real PHP 8 bugs the tests hadn't reached:
    - **No user or role could be saved.** The regex behind `AppModel::_validAlias()` and `_validName()` has a range, `\p{Nd}-_`, that PCRE2 (PHP 7.3+) rejects, so the validation always failed. A new test, `AdminUsersTest`, failed first and passes after the fix.
    - **`mysqli_client_encoding()`** was removed in PHP 8.0.
    - **A crash introduced by Rector:** it turned a `get_class()` into `::class` on an error path where the value is empty. PHPStan flagged it.
    - **Smaller items:** a typo (`$attribues`), an undefined `$model`, and `compact()` of variables that never exist.

    The old `mysql_*` driver, which can't run on PHP 7+, was removed. PHPStan now runs at **level 5**. The 3,696 findings the 2012 code already had are recorded in `phpstan-baseline.neon`: about 1,040 are CakePHP's runtime properties like `$this->Session`, and most others come from `extract()` and outdated docblocks. New and changed code has to pass.

8. **Passwords** are stored with `password_hash()` (bcrypt, through `PASSWORD_DEFAULT`) and checked with `password_verify()`. Nobody has to reset a password: an old SHA1 hash is checked one last time at login and replaced on success. The change is in CakePHP's `AuthComponent`, where CakePHP itself later added password hashers (2.4+).
    - **Login works differently.** Before, Auth hashed the posted password and looked for a row with that username **and** that hash. A `password_hash()` hash has a random salt, so it can't be matched in SQL. The user is now found by username, and the password is checked in PHP.
    - **The new hash is saved with model callbacks off.** Saving only the password makes the ACL behavior and `User::afterSave()` rebuild the user's ACL record without its role.
    - **Every place that writes a password moved too:**
        - The admin "reset password" screen checks the current password with `checkPassword()`.
        - The "forgot password" flow stores a `password_hash()` hash.
        - The installer and the `croogo password` shell no longer write SHA1.
    - **`PasswordHashingTest`** failed first, then passed with the fix.
    - **PHPStan:** the baseline went from 3,696 to 3,683 findings. `identify()`'s docblock now says it can return `null` or `false`.
    - **Not done, on purpose:**
        - A login for an unknown username skips the hash check, so it answers faster. That isn't hidden, because Croogo already shows every username on public profile pages (`/user/<name>`).
        - The `password` column stayed `varchar(100)`. That fits bcrypt (60 characters) and Argon2id (about 97). PHP recommends 255 for future algorithms. *Widened later,* see [Follow-ups](#follow-ups-after-the-php-83-step).

9. **Security fixes,** one commit each and each with its test, for the findings picked in step 1. They're described under [Security findings](#security-findings): findings 2, 4, 6 and 8 were fixed in code, and the File Manager was removed for findings 3 and 5.

The tag `php83` marks the end of this step.

## Continuous integration

[`.github/workflows/ci.yml`](.github/workflows/ci.yml) runs on every push and pull request. It uses the same Docker Compose services as a local run, in two jobs:

- **Characterization tests:** builds the stack, waits for the database, and loads every class on PHP 8.3 with `tools/check-declarations.php`, which fails the job if one doesn't load. Then it runs the characterization tests. If anything fails, the job prints the app's and the database's logs.
- **PHPStan and Rector:** PHPStan at level 5 against the baseline. Then Rector's dry run, which fails if new or changed code misses one of the PHP 8.3 rules the rest of the code already follows.

A long-running local setup hides three things that a fresh CI machine runs into:

- **A database that isn't ready yet.** On its first start, MySQL loads the sample data into a temporary server with networking off, then restarts. `compose.yaml` gives the database a healthcheck that pings it over TCP, and the app starts only once it answers. This also helps locally, after `docker compose down -v`.
- **File ownership.** On Linux the checkout belongs to the runner's user, and PHP runs as `www-data` (Apache's at first, PHP-FPM's since step 6), so the workflow makes `app/webroot/uploads` writable before the attachment tests. Docker Desktop on Windows and macOS doesn't enforce this. On Linux, do the same locally.
- **Files git doesn't track.** The first run failed in PHPStan: `cake/basics.php` includes `app/config/database.php`, which git ignores, and locally Docker had left an empty placeholder there. A rehearsal on a fresh clone missed it, because it started the app before running PHPStan. PHPStan now skips the "included file exists" check for that one include, which only runs after a `file_exists()` check.

**Rector joined CI after one leftover was settled.** Its dry run still proposed `get_class($var)` to `$var::class` in `Debugger::exportVar()`. Rector had made that change, and the PHPStan fixes turned it back, because the docblock then said `$var` was a string, so PHPStan rejected `::class`. The docblock now says `mixed`, and the line sits under `case 'object':`, so the change is safe and is applied. Checked by hand: `exportVar()` still prints an object's class and properties.

## Follow-ups after the PHP 8.3 step

Smaller problems noticed while working on step 4, each fixed in its own commit with its test. The before/after table above describes the `php83` tag; these come after it.

- **An attachment without an extension was listed with its whole name as the extension.** `FilemanagerHelper::filename2ext()` compared the array from `explode()` with 1 instead of counting it, so the check was always false. New uploads always keep an extension (security finding 4), so the test changes the stored name in the database to stand for an older upload. It failed first with `text/plain (notes-…)` and now shows `text/plain (file)`. PHPStan had both findings in the baseline, which went from 3,524 to 3,522.
- **Every spelling of a username had its own five login attempts.** The lockout counted failures under the username as typed. MySQL compares usernames with the column's collation, `utf8_unicode_ci`, which ignores case, accents and trailing spaces. `ADMIN`, `ÀDMÍN` and `admin   ` all log in to `admin`, checked by hand in SQL. Trailing spaces alone gave an attacker an unlimited supply of spellings, so the lockout didn't really limit password guessing.
    - **The fix:** before counting, `UsersController` asks the database which account the typed username belongs to, and counts under the stored name. Lowercasing in PHP wouldn't have matched every rule of the collation. A username that matches no account is still counted as typed.
    - **The test** fails five times with five spellings of one account, then expects the right password to be refused. Before the fix, it logged in.
    - **PHPStan:** the fix uses the controller's `User` model, which PHPStan didn't know about. Describing it (`@property User $User`), instead of adding to the baseline, showed that `Model::field()` and `Model::find()` had wrong docblocks, and that `find()` repeated an `is_string()` check that could never fail. Correcting those took the baseline from 3,522 to 3,441, in its own commit before the fix.
- **The key behind every form token was mostly predictable.** `Security::generateAuthKey()` makes the key for CakePHP's form tokens, which also guards the admin's delete links (`token:…`). It was the SHA-1 of `CakeText::uuid()`, a time-based UUID built from the clock, the process ID and the server's address, with only 26 random bits from `mt_rand()`, which isn't a secure generator.
    - **The fix:** `generateAuthKey()` now returns 20 bytes from `random_bytes()`, as hex: the same 40-character format, but random. `uuid()` stays as it was, because its other uses are identifiers, not secrets. Its docblock now says so.
    - **Checked by hand,** because nothing about it shows over HTTP. Three UUIDs made in a row shared the same seconds, process ID and address, and with the same `mt_rand()` seed, two UUIDs differed only in their microseconds. After the fix, the keys don't depend on `mt_rand()`. All 29 tests still pass, and every form submission and admin delete in them goes through these tokens.
    - **PHPStan:** `uuid()`'s docblock said `@return RFC 4122 UUID`, which PHPStan read as a class named `RFC`. Correcting it and dropping the old code took the baseline from 3,441 to 3,437.
- **The `password` column is 255 characters wide.** It was `varchar(100)`, enough for bcrypt and Argon2id, but `PASSWORD_DEFAULT` can move to a stronger algorithm with a longer hash, and PHP's documentation recommends 255. A longer hash in a 100-character column would be cut off, and nobody could log in.
    - **Fresh installs:** `croogo.sql`, which Docker loads, and `schema.php`, which the installer uses.
    - **Existing databases:** `app/config/schema/sql/upgrades/2026-11-12-widen-password-column.sql`. Checked by hand on this database: the column changed, the stored hashes stayed intact, and a second run changed nothing.
    - **The test** reads the column's length from MySQL's `information_schema`. It failed with 100 first.
- **"At least 6 characters" was checked against the hash.** CakePHP 1.3's `AuthComponent` hashed every posted password as soon as a request started, before the `User` model validated anything. The rule only ever saw a 60-character hash, so it never failed: an admin could create a user with an empty password, and both resets accepted any length. The original Croogo had the same bug with 40-character SHA-1 hashes.
    - **The fix,** the same change CakePHP 2.0 made: `AuthComponent` leaves posted passwords as typed, and `User::beforeSave()` hashes the password after validation has passed. One rule now covers the admin's "add user" form, both resets, and registration. `AuthComponent::password()` became static, so the model can call it.
    - **Hashes are made exactly once.** The "forgot password" reset and the installer now pass the typed password, which the model hashes. The rehash at login already saves with callbacks off, so `beforeSave()` doesn't hash it again.
    - **The tests:** the admin adds a user with an empty and with a 3-character password, and both resets try `abc`. All failed first: the user was created, and the reset was accepted. Now each is refused with the rule's message, and a refused reset keeps the old hash.
    - **Each layer checked:** without `beforeSave()`, passwords were stored as typed and 4 tests failed. With `AuthComponent` hashing early again, they were hashed twice and 6 failed.
    - **Checked by hand:** the installer's path, a `saveField()` of the password, isn't reached by the tests, because Docker seeds the database. Run through the `User` model on a throwaway user, it stored one bcrypt hash that verifies.
- **`app/webroot/js/vendors.php` is removed.** It's a CakePHP 1.2 script that served JavaScript files named in its URL from `app/vendors/javascript/`. That folder doesn't exist, so it only ever answered an empty 404. Still, a script that reads files by name has no place in the web root. Found while inventorying finding 7. Its address now gets the error page, like any unknown address, and a test in `KnownIssuesTest` checks that. The test failed first on the empty 404.

## nginx and PHP-FPM (step 6)

Apache with mod_php ran PHP inside the web server. Now nginx, the `web` service, serves the files in `app/webroot` itself and passes PHP requests to a separate PHP-FPM container, `php83`, over FastCGI. This step changes only the server, so that if a later step breaks something, the server can be ruled out. The 33 characterization tests passed without any change on the first run. Only the address they talk to moved from `php83` to `web`, in `compose.yaml`.

**What nginx repeats,** because it doesn't read `.htaccess` files ([`docker/nginx/default.conf`](docker/nginx/default.conf)):

| Under Apache | Under nginx |
| --- | --- |
| The root `.htaccess` rewrites every request into `app/webroot/`. | `app/webroot` is the document root. It's also the only folder the nginx container gets, read-only. |
| `app/webroot/.htaccess` sends anything that isn't a file or a folder to `index.php?url=…`. | `try_files $uri $uri/ /index.php?url=$uri&$args`. A missing `.php` file is an unknown address too, as before. |
| `uploads/.htaccess` turns PHP off and refuses script extensions. | `location ^~ /uploads/` never passes anything to PHP, and script extensions get 403. |
| Debian's Apache configuration refuses `.ht*` files. | The same, with `deny all`. |

**Three things that would have broken quietly:**
- **The environment.** PHP-FPM starts its workers with an empty environment by default (`clear_env`). The app reads `CROOGO_DEBUG` and its secrets with `getenv()`, so it would have run in production mode with CakePHP's published salt, and refused to start. `docker/php83/croogo-pool.conf` keeps the container's environment, as mod_php did.
- **Upload size.** nginx refuses request bodies over 1 MB unless told otherwise; Apache had no limit of its own. It's set to 8 MB, PHP's `post_max_size`.
- **Start-up order.** PHP-FPM gets a healthcheck, and nginx starts only once it accepts connections, so the first requests don't get a 502.

`SERVER_NAME` is set to the host name the browser asked for, as Apache did. Croogo uses it in the registration email's sender address.

**Checked by hand:**
- **`uploads/`:** a PHP probe there answered 403. With the deny rule removed, nginx served its source as text. With `^~` removed as well, PHP ran it and printed `php-exec-42`. Both `.htaccess` files answered 403.
- **The environment:** a temporary probe showed the four `CROOGO_*` variables reach PHP, and that one that isn't set stays unset.
- **Production mode,** in throwaway containers on ports 8098 and 8099. With no variables at all, the site answered 500 and logged which ones to set. With only its own secrets, it worked.

## Moving the contact form to Symfony (step 7, in progress)

The first area to move is the public contact form at `/contact`. It's small, and it uses the parts of Symfony most applications need: forms, validation, CSRF protection and the mailer.

**Decided before any Symfony code:**
- **The page layout stays Croogo's.** Croogo's sidebar is built from shortcodes (`[menu:meta]`, `[vocabulary:categories]`, `[node:recent_posts …]`), so rebuilding it in Twig would mean a second copy of the layout to keep in sync. Instead, Symfony will fetch the layout from Croogo and put its form inside, a technique called transclusion. Rebuilding the layout can come later, when more of the site has moved.
- **The escaping bug is fixed in Croogo first,** so the tests pin the right behavior and Symfony never has to copy a bug to pass them.
- **Akismet and the captcha aren't moved.** Both are off in the sample data. The captcha is reCAPTCHA v1, which Google shut down in 2018, and Akismet needs an API key. A modern replacement can be its own step.

**Done so far, in Croogo:**
- **More tests first.** `ContactFormTest` gathers everything about the form. Before any change, it recorded the exact email (the link back, one line per field, the visitor's IP address, the sender) and the validation messages, and that nothing is sent until they pass. These are the tests Symfony will have to pass unchanged.
- **The contact form had the comments' escaping bug** (security finding 2): it ran `htmlspecialchars()` on the name, subject and message before validating, emailing and storing them. The plain-text email showed `&amp;` and `&lt;`, and archived messages were stored as entities.
    - **Fixed in three layers:** the controller keeps the text as typed. The `From` header quotes the visitor's name when it contains a character that means something in an address. The admin's message list escapes the name, email and subject.
    - **The quoting matters.** The encoding had also been keeping the name harmless in the `From` header. Without the quotes, a visitor named `Boss <boss@your-site.com>` would be the sender as far as Mailpit, and any mail client, can tell: the check showed `boss@your-site.com`.
    - **The tests:** one checks the email's text and sender with `O'Brien & "Sons" <boss@your-site.com>` as the name. Another switches archiving on through the admin, then checks the stored row and the admin list. Both failed first. Each layer was then removed on its own, and its test failed.

**The Symfony app,** in `symfony/`:
- **A Symfony 7.4 skeleton,** the long-term support version, created with `composer create-project symfony/skeleton`. It has only the framework's core: the front controller `public/index.php`, the kernel, the console and the configuration. Each package comes in with the part that needs it.
- **Its own PHP-FPM container,** the `symfony` service in `compose.yaml`. It has the same `clear_env = no` fix as Croogo's container, because Symfony reads `APP_ENV` and `APP_SECRET` from the environment. `var/`, which holds the cache and logs, is a volume, owned by PHP-FPM's user.
- **No secret in git.** Symfony's recipe generated a random `APP_SECRET` into a committed `.env.dev`. That file is removed; `compose.yaml` sets a throwaway local value, as it does for Croogo's salts.
- **Not routed yet.** nginx still sends every address to Croogo, so the 37 tests ran against Croogo as before. Checked by hand: a temporary nginx location for one throwaway address reached Symfony through PHP-FPM and got its dev-mode "No route found" page, showing that the environment reaches the workers. Then the config was restored.

**The layout Symfony borrows,** `/legacy-layout` in Croogo (`LegacyLayoutController`):
- **What it renders:** Croogo's normal page, with `%%LEGACY_LAYOUT_TITLE%%` as the title and `<!--%%LEGACY_LAYOUT_CONTENT%%-->` as the content. Symfony will fetch it, put its own title and content in their place, and cache the result for a short time.
- **Rendered for the visitor's page.** The main menu marks the current page, and blocks can be limited to some pages. Both read the path being viewed, so the page to render for is passed as `?path=/contact`.
    - Auth and CakePHP's Security component check the request at its own address first. Inside the action, the path is switched, and the Croogo component rebuilds the blocks for it.
    - Only a plain path on this site is accepted. `//evil.example`, `javascript:` and markup fall back to the home page.
- **Rendered for an anonymous visitor.** The request comes from Symfony, not from the visitor's browser. That matters only if a block is limited to some roles; none are in the sample data.
- **The tests** check the placeholders and the sidebar, the marked menu link for two paths, the rejected paths, and a block limited to `/contact` through the admin. All failed first, and removing each part of the action (the path switch, the block rebuild, the path check) failed its test.
- **A `noindex` header** keeps search engines from listing this page. Symfony doesn't copy headers, only the HTML.
- **PHPStan:** the new controller hit a false positive. PHPStan saw `parent::beforeFilter()` as a call with no effect, because CakePHP's `Controller` declares its hooks empty. Marking `beforeFilter()`, `beforeRender()` and `afterFilter()` as `@phpstan-impure` at the source fixed the new code and removed the same false positive from 12 places in the baseline, from 3,437 findings to 3,425.

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

    *Fixed in step 4 (item 8 of the upgrade).*
2. **XSS through the commenter's website.** The comment form accepts any value as the website, and the public page turns it into the link on the commenter's name. A visitor who clicks that name runs the script.

    *Confirmed:* a comment posted without logging in, with the website `javascript:alert(1)`, was published immediately and rendered as `<a href="javascript:alert(1)">`.

    *Fixed in step 4,* in two layers:
    - **On input:** the `Comment` model only accepts an `http://` or `https://` address, or a path on this site such as the sample admin's `/about`. Logged-in users' comments take the website from their profile, so that path has to keep working. An address without a scheme, like `example.com`, is now refused with a message.
    - **On output:** the name becomes a link only for those addresses. Comments saved before the check can still hold anything: this database already had 11 `javascript:` websites, left by earlier test runs.

    *Corrected after testing:* the code reading also suspected the comment name and body. They turned out not to be exploitable, because `CommentsController` runs `htmlspecialchars()` on them **before saving**. That's escaping at the wrong layer, though:
    - The database holds HTML entities instead of the text people typed.
    - Pages that escape again show them double-encoded, for example `&amp;lt;i&amp;gt;`.
    - Any other way of writing comments would skip the protection.

    The fix escapes on output and stores raw text.

    *Fixed in step 4:*
    - **Saving:** `CommentsController` stores the name and body as typed.
    - **Showing:** each view escapes for its own output.
        - The comment element uses `h()` before `autoLink()`. That gives the same HTML as before, because the escaping only moved from saving to showing.
        - The admin list escapes the name, the email, and the body in its tooltip. That tooltip link uses `'escape' => false` for its icon, which also switches off escaping of its attributes.
        - The RSS feed already escaped everything, so it now shows the text once instead of double-encoded.
        - The notification email is plain text, so it now shows what was typed instead of entities.
    - **Existing rows:** `app/config/schema/sql/upgrades/2026-11-09-decode-comment-text.sql` turns them back into plain text, once.
        - A round trip checked it: 14 strings encoded with PHP 8's and PHP 5's `htmlspecialchars()` rules came back exactly.
        - On this database, it decoded 13 names and 1 body.
    - **The test** checks the stored row, the public page with and without a website, and the admin list. It fails if the public views stop escaping. It also fails if only the admin list stops escaping.
3. **The file manager upload accepts any path and any file type.** `FilemanagerController::admin_upload()` takes the target folder from the URL and doesn't run the `_isEditable()` check that `admin_editfile()` uses. It also doesn't check the extension, so an admin session could write a `.php` file anywhere the web server can write, including the web root.

    *Confirmed in the code, but not reachable with this CakePHP version.* Every File Manager form (upload, new file, new folder, rename) is rejected with "Security Error", in a real browser too. The cause:
    - **CakePHP 1.3.18** (April 2014) started tying each form's security token to the URL it posts to.
    - **Croogo's File Manager** builds its form URLs as absolute addresses with a `?path=` query (`http://host/admin/filemanager/upload?path=…`).
    - **`FormHelper::_lastAction()`** prepends the base URL to them a second time. The token ends up hashed with a broken path, so it never matches the request.

    The hole is still in Croogo's code; it only stays closed by accident, and an older 1.3 core opens it again. A small upgrade with a security fix silently broke a feature, which is exactly what characterization tests are meant to catch.

    *Resolved in step 4 by removing the File Manager,* together with finding 5 (details there).
4. **Attachment uploads aren't validated.** `AttachmentsController::admin_add()` saves uploads into `webroot/uploads` with the client's file name. It trusts the MIME type the client sends and has no extension allow-list. It also contains a logic bug: `explode(...) > 0` compares an array with a number.

    *Confirmed:* a file `probe.php` uploaded as an attachment with the type `image/png` was saved to `/uploads/probe.php`, and requesting it **ran the PHP code**. Anyone with an admin session (or who steals one) can run code on the server.

    *Fixed in step 4,* in three layers:
    - **Allow-list:** only images, PDFs, office documents, text, CSV, ZIP, MP3 and MP4. Anything a web server or a browser could run as part of this site is left out: PHP, CGI scripts, HTML, SVG and JavaScript. A refused file is never written to disk.
    - **Safe names:** the stored name keeps only lowercase letters, digits and hyphens, then the extension. `Probe 1.php.png` becomes `probe-1-php.png`, which can't run even on Apache setups that treat any name containing `.php.` as code. A name already taken gets a random suffix, not a UUID prefix. The title keeps the name as uploaded, and the `explode(...) > 0` bug is gone.
    - **Real type:** the MIME type is detected from the file's content. In the test, a PHP file sent as `image/png` is stored as `text/x-php`.
    - **No PHP in `uploads/`:** `webroot/uploads/.htaccess` switches PHP off and denies script extensions. It covers any other way a file could get there.
        - Checked by hand, because the HTTP tests can't put a file into that folder. A PHP probe placed there answered **403** with the file and printed `php-exec-42` without it.
        - Croogo's `app/.gitignore` ignores the uploads folder, so this one file is added to git on purpose.
        - nginx ignores `.htaccess`, so step 6 repeats the rule in the nginx config. See [nginx and PHP-FPM](#nginx-and-php-fpm-step-6).
5. **The file manager can edit any file under `app/`,** PHP code included. It is a feature, but in practice it means code execution for anyone with an admin session. The modernized version should remove it or limit it to theme assets. `_isEditable()` also matches by prefix, so `/app` would match `/app_backup`.

    *Resolved in step 4 by removing the File Manager,* together with finding 3:
    - **Code:** the controller and its five views are deleted. `FilemanagerHelper` stays, cut down to the two methods the Attachments screens use (a file's extension and the icon for its type). Its other methods only built links into the File Manager.
    - **Admin menu:** the "File Manager" link under Media is gone. Its addresses now answer with the same error page as any unknown address.
    - **Access control records:** CakePHP's ACL keeps a record (an ACO) for every controller and action, and the permissions screen lists them all. Croogo's "Generate" only ever adds records, so the File Manager's 11 would have stayed for good.
        - **Fresh installs:** they're removed from the sample data (`croogo_data.sql`) and from the installer's copy of it (`aco_data.php`). The table is a nested set, so every number after them moved down; a script did the renumbering, not a hand edit.
        - **Existing databases:** `app/config/schema/sql/upgrades/2026-11-12-remove-file-manager-acos.sql` deletes them, with any permissions given on them, and closes the gap. It looks the node up by name, so on a database without it, it does nothing.
        - **Checked by hand:** the upgrade ran twice on the existing database, and the second run changed nothing. A fresh database loaded from the new sample data came out identical to it, row for row. Both passed a nested set check: no number used twice or missing, and every action inside its controller.
        - Croogo's own SimpleTest fixtures still list them. That suite isn't bundled or run here, so its fixtures were left as they were.
    - **The test:** `KnownIssuesTest` no longer expects the forms to be rejected. It checks that the admin menu has no link, that the File Manager's addresses answer with the error page, and that the permissions screen no longer lists it. Each layer was removed on its own: the test failed on the menu link first, then on the address, then on the permissions screen, and passed once the records were gone.
    - **PHPStan:** the baseline went from 3,568 to 3,524 findings.
6. **Insecure defaults:**
    - `debug` is 1 in `config/core.php`.
    - `Security.salt` and `Security.cipherSeed` ship as CakePHP's public defaults. The installer replaces them.
    - The installer creates `admin` with the password `password`.

    *Fixed in step 4:*
    - **Debug:** `core.php` reads `CROOGO_DEBUG`, so production mode is the default and development has to opt in. With debug off, CakePHP's old web test runner `/test.php` stops at "Debug setting does not allow access". With debug on, it showed a PHP parse error with server paths.
    - **The web test runner is removed.** `/test.php` could only fail: SimpleTest isn't bundled, and CakePHP's own test library in `cake/tests/` was never ported to PHP 8. Its address now gets the error page, like any unknown address, and a test in `KnownIssuesTest` checks that. The test failed first on the parse error. `cake/tests/` stays where it is, unreachable from the web: the root `.htaccess` sends every request into `app/webroot/`.
    - **Secrets from the environment:** `CROOGO_SECURITY_SALT` and `CROOGO_CIPHER_SEED` replace the values in `core.php`, so they don't have to be stored in the repository. An installed site with debug off refuses to start with the published values.
        - Before installation it still starts, because the installer is what replaces them.
        - Checked by hand in throwaway containers: production mode without its own secrets answered 500 and logged which variables to set. With them, the site worked.
    - **Changing the salt without locking anyone out:** old SHA1 hashes depend on the salt. `CROOGO_LEGACY_SALT` keeps them usable until each user's next login replaces the hash (item 8 of the upgrade).
        - The local setup does exactly this: the site has its own salt, and the sample data's hashes use the published one.
        - `PasswordHashingTest` failed after the salt changed and passed once the legacy salt was read.
    - **The installer** generates the salt and seed with `random_bytes()` / `random_int()` instead of `mt_rand()`. It gives `admin` a random password and shows it once on its last page.
        - This part isn't covered by a test: the Docker setup seeds the database and never runs the installer.
        - The local sample data still has `admin` / `password`.
    - **PHPStan:** CakePHP's docblocks for `Configure::write()` and `Configure::read()` were wrong: one only accepted an array, the other always returned a string. Correcting them took the baseline from 3,674 to 3,568 findings.
7. **Old frontend libraries** bundled for the admin and TinyMCE have known issues.

    *Inventoried after step 4.* The versions come from each file's own header. The advisories come from [OSV](https://osv.dev), queried on 2026-11-12 for these exact versions.

    | Library | Version | Loaded on | Advisories in OSV | All fixed by |
    | --- | --- | --- | --- | --- |
    | jQuery | 1.4.2 (2010) | every page | 6, all cross-site scripting: CVE-2011-4969, CVE-2012-6708, CVE-2015-9251, CVE-2019-11358, CVE-2020-7656, CVE-2020-11023 | 3.5.0 |
    | jQuery UI | 1.8.4 (2010) | admin | 5, all cross-site scripting: CVE-2010-5312, CVE-2016-7103, CVE-2021-41182, CVE-2021-41183, CVE-2021-41184. OSV lists 2 more, for the Tooltip and checkboxradio widgets, which 1.8 doesn't have. | 1.13.0 |
    | TinyMCE | 3.3.8 (2010) | admin content forms, when WYSIWYG editing is on | 19 whose version ranges include 3.3.8, almost all cross-site scripting. Several are in features 3.x doesn't have. | the current major version is 8 |
    | Thickbox | 3 (2007) | admin | none | |
    | Superfish 1.4.8, Supersubs 0.2b, hoverIntent r5 | 2007-2010 | every page (the menus) | none | |
    | cookie, elastic 1.6.1, field 0.9.2, tipsy 1.0.0a, slug, uuid | 2006-2010 | admin | none | |

    - **"None"** means OSV has no advisories, for any version, under the plugins' npm package names (`superfish`, `supersubs`, `jquery-hoverintent`, `thickbox`, `jquery.cookie`, `tipsy` and others). Unmaintained code can still have problems nobody reported.
    - **What this means here.** The jQuery and jQuery UI advisories need the site's own code to pass untrusted input to them: `$(location.hash)`, HTML from a URL or a visitor, a cross-domain AJAX request, or one of the affected widget options. Croogo's own scripts don't. Their only AJAX is three same-site requests in the admin, to URLs from the admin's own links, and they don't use the datepicker, dialogs or `.position()`. That comes from reading `admin.js`, `theme.js` and the other scripts; it says nothing about themes or plugins added later. TinyMCE edits content that only admins can write.
    - **Why it isn't simply upgraded.** The plugins depend on APIs that jQuery 1.9 removed: the elastic plugin calls `.live()`, and Superfish reads `$.browser`. TinyMCE 3 to 8 means rewriting the editor integration. That's a frontend step of its own, so for now the finding is documented, not fixed.
8. **Anyone can lock out any account.** After 5 failed logins in 5 minutes, a username is blocked for everyone, including its owner with the right password (`UsersController::beforeFilter`). Knowing that the admin is called `admin` is enough to keep them out.

    *Found while writing the tests:* the wrong-password test locked out the admin account the other tests use.

    *Fixed in step 4:* failed attempts are counted per username **and** IP address, so whoever fails only locks themselves out.
    - **Which address:** `$_SERVER['REMOTE_ADDR']`, the other end of the connection, which a client can't choose. Two CakePHP helpers looked like the obvious choice, and both would have let an attacker send a new address with every attempt and never be locked out:
        - `RequestHandler::getClientIP()` prefers the `Client-IP` and `ClientAddress` request headers, even in its default "safe" mode.
        - `env('REMOTE_ADDR')` returns the `PC-Remote-Addr` header instead when the connection comes from the server's own address, for example from a reverse proxy on the same machine.
    - **The cache key** is a hash of the username and the address, so what was typed as a username no longer ends up in a cache file name.
    - **The tests:** a new test fails five times through the relay, then checks that the relay's address is locked and that the owner still logs in directly. It failed before the fix: the owner was locked out too. A second test sends a new forged address in `Client-IP`, `ClientAddress` and `X-Forwarded-For` with each attempt and expects the lockout anyway.
    - **Each layer checked:** with `getClientIP()` swapped in, the forged-header test failed. The `env()` behavior can't be reached over the network from the tests, because the request would have to come from the server's own address. It was checked by hand instead: with the same address for client and server, `env('REMOTE_ADDR')` returned the forged header.
    - **Behind a reverse proxy,** `REMOTE_ADDR` is the proxy's address, and every visitor would share one counter per username again. The web server then has to restore the client's address from the proxy's header, trusting only that proxy: `mod_remoteip` in Apache, the realip module in nginx.
    - **Trade-off:** an attacker spread over many addresses gets 5 attempts per address every 5 minutes. Rate limiting at the proxy, or a higher limit per username across all addresses, would cover that. It isn't built here.
    - **Fixed later:** the counter was keyed on the username as typed, while MySQL logs `ADMIN`, `ádmin` and `admin   ` in to the same account. See [Follow-ups](#follow-ups-after-the-php-83-step).

**Fixed in this project:**
- **Fixed in code:** 1, 2, 4, 6 and 8. For 8, failed attempts are counted per username *and* IP address, so an attacker only locks out themselves.
- **Removed:** findings 3 and 5 are resolved by removing the File Manager completely, rather than repairing its forms and then hardening them. A CMS doesn't need a web-based editor for server files, and media uploads stay available through Attachments, where finding 4 gets fixed.
- **Documented only:** 7, now with an inventory, is left for a later frontend step.

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
| 6 | Apache with mod_php replaced by nginx + PHP-FPM, keeping the `.htaccess` rules (friendly URLs, no PHP in `uploads/`). Done, see [nginx and PHP-FPM](#nginx-and-php-fpm-step-6) | |
| 7 | One area moved to Symfony 7 behind the same nginx, on the same database (strangler fig). In progress: the contact form, see [Moving the contact form to Symfony](#moving-the-contact-form-to-symfony-step-7-in-progress) | |

## How this was built

Florentina Opris directed the work: she chose the project and the approach, set the rules, reviewed every change, and ran and tested the app. Claude Code (Anthropic) wrote most of the code and the scans behind this analysis under her direction. The CakePHP 1.3 core changes for PHP 8.3 are written fresh for this repository.
