-- Run ONCE on a database from before commit "fix: store comment text as
-- typed and escape it when shown". A fresh install doesn't need it.
--
-- Croogo 1.3 ran htmlspecialchars() on visitors' comment names and on every
-- comment body before saving them. The pages now escape the text when they
-- show it, so the stored entities are turned back into what people typed.
--
-- - &amp; is replaced last. Someone who typed "&lt;" was stored as "&amp;lt;"
--   and gets "&lt;" back, not "<".
-- - &#039; is what PHP 8.1+ makes of a single quote. PHP 5 left it as it was.
-- - Logged-in users' names came from their profile and were never encoded.
-- - Running it a second time would decode entities that people typed.
--
-- Croogo caches comment lists, so clear app/tmp/cache afterwards.

UPDATE comments SET body = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
    body, '&lt;', '<'), '&gt;', '>'), '&quot;', '"'), '&#039;', ''''), '&amp;', '&');

UPDATE comments SET name = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
    name, '&lt;', '<'), '&gt;', '>'), '&quot;', '"'), '&#039;', ''''), '&amp;', '&')
WHERE user_id = 0;
