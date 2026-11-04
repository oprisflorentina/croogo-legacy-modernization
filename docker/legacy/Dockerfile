# The original app as it ran around 2012: PHP 5 with mod_php under Apache.
# PHP 5.6 is the oldest official image Docker 29 can still pull.
FROM php:5.6-apache

# CakePHP 1.3 talks to MySQL through the old mysql extension,
# and its friendly URLs need mod_rewrite plus .htaccess overrides.
RUN docker-php-ext-install mysql \
    && a2enmod rewrite \
    && printf '<Directory /var/www/html>\n    AllowOverride All\n</Directory>\n' \
        > /etc/apache2/conf-enabled/allow-override.conf \
    && printf 'date.timezone = UTC\n' > /usr/local/etc/php/conf.d/timezone.ini

COPY entrypoint.sh /usr/local/bin/croogo-entrypoint
RUN chmod +x /usr/local/bin/croogo-entrypoint

ENTRYPOINT ["croogo-entrypoint"]
CMD ["apache2-foreground"]
