# Test runner. It talks to the app over HTTP only, so it runs on modern
# PHP no matter which PHP version serves the app.
FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer/composer:2-bin /composer /usr/bin/composer

WORKDIR /tests
