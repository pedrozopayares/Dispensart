# syntax=docker/dockerfile:1
# Imagen de la API (design D1). Contexto de construcción: software/.
# Etapas: base (PHP-FPM + extensiones) -> dev (Composer y herramientas, servicio api-tools).
# La etapa prod (vendor sin dev + Nginx + supervisord) se agrega en la tarea 4.1.

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
