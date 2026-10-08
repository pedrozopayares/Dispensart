# syntax=docker/dockerfile:1
# Imagen de la API (design D1). Contexto de construcción: software/.
# Etapas: base (PHP-FPM + extensiones) -> dev (Composer y herramientas, servicio api-tools)
#         base -> vendor (dependencias sin desarrollo) -> prod (FPM + Nginx bajo supervisord, no root).

ARG PHP_IMAGE=php:8.5.11-fpm-alpine3.24
ARG COMPOSER_IMAGE=composer:2.10.3

FROM ${COMPOSER_IMAGE} AS composer

# --- base: PHP 8.5-FPM con pdo_pgsql, sin herramientas de compilación ---
FROM ${PHP_IMAGE} AS base

ARG APP_UID=1000
ARG APP_GID=1000

# Dependencias de compilación solo durante la instalación; luego se retiran.
RUN set -eux; \
    apk add --no-cache libpq icu-libs; \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev icu-dev; \
    docker-php-ext-install -j"$(nproc)" pdo_pgsql intl bcmath; \
    apk del .build-deps; \
    php -m | grep -q pdo_pgsql

# Usuario no root con UID fijo (bind mounts y volúmenes predecibles).
RUN set -eux; \
    addgroup -g "${APP_GID}" app; \
    adduser -D -u "${APP_UID}" -G app -h /home/app app; \
    mkdir -p /app; \
    chown app:app /app

WORKDIR /app

# --- dev: Composer y dependencias de desarrollo; el código llega por bind mount (./api:/app) ---
FROM base AS dev

# git y unzip para Composer; pcov como driver de cobertura para Pest.
RUN set -eux; \
    apk add --no-cache git unzip; \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers; \
    pecl install pcov; \
    docker-php-ext-enable pcov; \
    apk del .build-deps; \
    rm -rf /tmp/pear

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_HOME=/home/app/.composer

USER app

CMD ["php", "-v"]

# --- vendor: dependencias de Composer SIN desarrollo y autoloader optimizado ---
FROM base AS vendor

RUN apk add --no-cache unzip
COPY --from=composer /usr/bin/composer /usr/local/bin/composer

USER app
ENV COMPOSER_HOME=/home/app/.composer

# Primero solo los manifiestos: la capa de vendor se reutiliza mientras el lockfile no cambie.
COPY --chown=app:app api/composer.json api/composer.lock ./
RUN --mount=type=cache,target=/home/app/.composer/cache,uid=1000,gid=1000 \
    composer install --no-dev --no-scripts --no-autoloader --no-interaction --no-progress --prefer-dist

COPY --chown=app:app api/ ./
# post-autoload-dump ejecuta package:discover sin paquetes de desarrollo.
RUN composer dump-autoload --no-dev --optimize --no-interaction

# --- prod: PHP-FPM + Nginx bajo supervisord, todo como usuario app (design D1) ---
FROM base AS prod

RUN set -eux; \
    apk add --no-cache nginx supervisor; \
    # Se reemplaza la configuración de pool de la imagen oficial (user www-data, puerto 9000).
    rm -f /usr/local/etc/php-fpm.d/*.conf /usr/local/etc/php-fpm.d/*.default; \
    cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini; \
    # Socket de FPM y estado persistente (APP_KEY, volumen api_state) con dueño no root.
    mkdir -p /run/app /var/lib/dispensart; \
    chown app:app /run/app /var/lib/dispensart; \
    chmod 0700 /var/lib/dispensart

COPY docker/api/nginx.conf /etc/nginx/nginx.conf
COPY docker/api/php-fpm.conf /usr/local/etc/php-fpm.d/app.conf
COPY docker/api/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/api/supervisord.conf /etc/supervisor/supervisord.conf
COPY --chmod=0755 docker/api/entrypoint.sh /usr/local/bin/entrypoint

# Código propiedad de root (solo lectura para el proceso); escribibles solo storage y bootstrap/cache.
COPY --from=vendor --chown=root:root /app /app
RUN set -eux; \
    mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
             storage/framework/sessions storage/framework/views storage/logs bootstrap/cache; \
    chown root:root /app; \
    chown -R app:app storage bootstrap/cache; \
    test ! -e .env; \
    test ! -d vendor/pestphp; \
    test ! -d vendor/larastan

USER app

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]
