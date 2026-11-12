# The modernized app on PHP 8.3, as PHP-FPM behind nginx (the web service in
# compose.yaml). Same extensions and settings as the Apache + mod_php image it
# replaced; only the way requests reach PHP changed.
FROM php:8.3-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install mysqli gd \
    && printf 'date.timezone = UTC\n' > /usr/local/etc/php/conf.d/timezone.ini

COPY --from=axllent/mailpit:v1.33.0 /mailpit /usr/local/bin/mailpit
RUN printf 'sendmail_path = "/usr/local/bin/mailpit sendmail -S mailpit:1025"\n' \
        > /usr/local/etc/php/conf.d/mail.ini

COPY croogo-pool.conf /usr/local/etc/php-fpm.d/zz-croogo.conf

COPY entrypoint.sh /usr/local/bin/croogo-entrypoint
RUN chmod +x /usr/local/bin/croogo-entrypoint

ENTRYPOINT ["croogo-entrypoint"]
CMD ["php-fpm"]
