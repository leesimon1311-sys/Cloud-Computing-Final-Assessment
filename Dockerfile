# ---------------------------------------------------------------------------
# Container image for the cloud deployment.
#
# The local environment runs Apache + PHP + MySQL installed by XAMPP. This
# image reproduces the same Apache + PHP stack on Linux, with the PostgreSQL
# driver added so the application can use the platform's managed database.
# ---------------------------------------------------------------------------
FROM php:8.2-apache

# PDO drivers for both engines, so one image runs against MySQL or PostgreSQL.
RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql pdo_pgsql \
 && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers

COPY docker/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html
COPY . /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
 && chown -R www-data:www-data /var/www/html

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
