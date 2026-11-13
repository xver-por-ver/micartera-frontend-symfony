ARG RELEASE_PHP=8.5.7
ARG BASE_OCI_IMAGE=php:${RELEASE_PHP}-fpm-alpine

ARG RELEASE_NODE=24.17.0
ARG HELPER_OCI_IMAGE=node:${RELEASE_NODE}-alpine
ARG RELEASE_OPENTELEMETRY=1.3.1

############################
# PHP extension build stage
############################
FROM ${BASE_OCI_IMAGE} AS php-ext-build
ARG RELEASE_OPENTELEMETRY
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
WORKDIR /var/www/html

RUN set -eux; \
    install-php-extensions \
        bcmath \
        intl \
        mbstring \
        pdo_mysql \
        opentelemetry-php/ext-opentelemetry@${RELEASE_OPENTELEMETRY} \
        protobuf; \
    php --ri opentelemetry > /dev/null

############################
# Composer vendor stage
############################
FROM php-ext-build AS vendorphp
WORKDIR /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --no-autoloader \
    --no-scripts

COPY . .
RUN composer dump-autoload \
    --no-dev \
    --optimize \
    --classmap-authoritative

############################
# Node deps stage
############################
FROM ${HELPER_OCI_IMAGE} AS depsnode
WORKDIR /var/www/html

COPY package*.json ./
RUN npm ci

############################
# Node build stage
############################
FROM ${HELPER_OCI_IMAGE} AS buildnode
WORKDIR /var/www/html

COPY --from=depsnode /var/www/html/node_modules ./node_modules
COPY package*.json webpack.config.js postcss.config.js ./
COPY assets ./assets
RUN npm run build

############################
# Final runtime image
############################
FROM ${BASE_OCI_IMAGE} AS runtime
WORKDIR /var/www/html

RUN set -eux; \
    apk add --no-cache \
        icu-libs

# Copy PHP extensions and config from build stage
COPY --from=php-ext-build /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=php-ext-build /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d

# Copy application
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendorphp /var/www/html/vendor ./vendor
COPY --chown=www-data:www-data --from=buildnode /var/www/html/public/build ./public/build

USER www-data
ENV APP_ENV=prod
ENV APP_DEBUG=0

EXPOSE 9000
CMD ["php-fpm", "-F"]
