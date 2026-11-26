FROM serversideup/php:8.5-fpm-nginx

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    PHP_OPCACHE_ENABLE=1 \
    TRUSTED_PROXY=off \
    HEALTHCHECK_PATH=/health

COPY --chown=www-data:www-data composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress --no-interaction

COPY --chown=www-data:www-data . .
RUN composer dump-autoload --no-dev --classmap-authoritative \
 && composer dump-env prod \
 && composer run-script --no-dev post-install-cmd
