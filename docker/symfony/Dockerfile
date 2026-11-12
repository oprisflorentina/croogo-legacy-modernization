# The Symfony app in symfony/, as its own PHP-FPM container. nginx (the web
# service) passes it the addresses that have moved from Croogo.
FROM php:8.3-fpm

# pdo_mysql for Doctrine, which uses Croogo's MySQL database. OPcache keeps
# the compiled PHP in memory between requests.
RUN docker-php-ext-install pdo_mysql opcache \
    && printf 'date.timezone = UTC\n' > /usr/local/etc/php/conf.d/timezone.ini

COPY symfony-pool.conf /usr/local/etc/php-fpm.d/zz-symfony.conf

COPY entrypoint.sh /usr/local/bin/symfony-entrypoint
RUN chmod +x /usr/local/bin/symfony-entrypoint

WORKDIR /var/www/symfony
ENTRYPOINT ["symfony-entrypoint"]
CMD ["php-fpm"]
