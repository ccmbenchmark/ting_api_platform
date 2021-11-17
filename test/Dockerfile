FROM php:7.2 as php

COPY --from=composer:1 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

RUN apt-get update && apt-get install -y curl wget git zip libpq-dev \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo mysqli pgsql pdo_pgsql mbstring \
    && mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

#COPY docker/apache.conf /etc/apache2/sites-enabled/000-default.conf


COPY . ./
RUN composer install --no-progress --prefer-dist



RUN chown -R www-data:www-data /var/www/html


ARG APP_DEBUG=true
ARG APP_ENV=dev

ENV APP_DEBUG=${APP_DEBUG}
ENV APP_ENV=${APP_ENV}

CMD php -S 0.0.0.0:80 -t public
