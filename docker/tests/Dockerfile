# Test runner. It talks to the app over HTTP, so it runs on modern PHP no
# matter which PHP version serves the app. pdo_mysql is for the tests that
# check what ends up in the database, such as stored password hashes.
FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql

COPY --from=composer/composer:2-bin /composer /usr/bin/composer

WORKDIR /tests
