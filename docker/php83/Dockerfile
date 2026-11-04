# The modernized app: same Apache + mod_php setup as the legacy image,
# on PHP 8.3, with mysqli instead of the removed mysql extension.
FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install mysqli gd \
    && a2enmod rewrite \
    && printf '<Directory /var/www/html>\n    AllowOverride All\n</Directory>\n' \
        > /etc/apache2/conf-enabled/allow-override.conf \
    && printf 'date.timezone = UTC\n' > /usr/local/etc/php/conf.d/timezone.ini

COPY --from=axllent/mailpit:v1.33.0 /mailpit /usr/local/bin/mailpit
RUN printf 'sendmail_path = "/usr/local/bin/mailpit sendmail -S mailpit:1025"\n' \
        > /usr/local/etc/php/conf.d/mail.ini

COPY entrypoint.sh /usr/local/bin/croogo-entrypoint
RUN chmod +x /usr/local/bin/croogo-entrypoint

ENTRYPOINT ["croogo-entrypoint"]
CMD ["apache2-foreground"]
