-- Run on a database from before commit "fix: make room for 255-character
-- password hashes". A fresh install doesn't need it. Running it a second
-- time changes nothing.
--
-- password_hash() with PASSWORD_DEFAULT can move to a stronger algorithm
-- with a longer hash in a future PHP version, and PHP's documentation
-- recommends a column of 255 characters. Croogo's was 100: enough for
-- bcrypt (60) and Argon2id (about 97), but not more. The column keeps its
-- other properties.

ALTER TABLE users MODIFY `password` varchar(255) COLLATE utf8_unicode_ci NOT NULL;
