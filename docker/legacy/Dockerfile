# The original app as it ran around 2012: PHP 5 with mod_php under Apache.
# PHP 5.6 is the oldest official image Docker 29 can still pull.
FROM php:5.6-apache

# Croogo's image helper needs GD with JPEG and PNG. The Debian 9 package
# archive behind this image is signed with expired keys, so instead of
# apt the three libraries are built from their official source releases,
# downloaded over HTTPS and pinned by SHA-256.
RUN set -eux; \
    mkdir -p /usr/src/libs && cd /usr/src/libs; \
    fetch() { curl -fsSL -o "$2" "$1" && echo "$3  $2" | sha256sum -c -; }; \
    fetch https://github.com/madler/zlib/releases/download/v1.3.1/zlib-1.3.1.tar.gz zlib.tar.gz \
        9a93b2b7dfdac77ceba5a558a580e74667dd6fede4585b91eefb60f03b72df23; \
    fetch https://ijg.org/files/jpegsrc.v9f.tar.gz jpeg.tar.gz \
        04705c110cb2469caa79fb71fba3d7bf834914706e9641a4589485c1f832565b; \
    fetch https://download.sourceforge.net/libpng/libpng-1.6.43.tar.gz libpng.tar.gz \
        e804e465d4b109b5ad285a8fb71f0dd3f74f0068f91ce3cdfde618180c174925; \
    for f in *.tar.gz; do tar -xzf "$f"; done; \
    (cd zlib-1.3.1 && ./configure --prefix=/usr/local && make -j"$(nproc)" && make install); \
    (cd jpeg-9f && ./configure --prefix=/usr/local && make -j"$(nproc)" && make install); \
    (cd libpng-1.6.43 && CPPFLAGS=-I/usr/local/include LDFLAGS=-L/usr/local/lib \
        ./configure --prefix=/usr/local && make -j"$(nproc)" && make install); \
    ldconfig; \
    rm -rf /usr/src/libs

# CakePHP 1.3 talks to MySQL through the old mysql extension, and friendly
# URLs need mod_rewrite plus .htaccess overrides.
RUN docker-php-ext-configure gd --with-zlib-dir=/usr/local --with-jpeg-dir=/usr/local --with-png-dir=/usr/local \
    && docker-php-ext-install mysql gd \
    && a2enmod rewrite \
    && printf '<Directory /var/www/html>\n    AllowOverride All\n</Directory>\n' \
        > /etc/apache2/conf-enabled/allow-override.conf \
    && printf 'date.timezone = UTC\n' > /usr/local/etc/php/conf.d/timezone.ini

COPY entrypoint.sh /usr/local/bin/croogo-entrypoint
RUN chmod +x /usr/local/bin/croogo-entrypoint

ENTRYPOINT ["croogo-entrypoint"]
CMD ["apache2-foreground"]
