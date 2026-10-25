# Image for the public demo (see render.yaml). Apache + mod_php serve public/,
# SQLite holds the data and docker/entrypoint.sh seeds it on every boot.

FROM php:8.4-apache-bookworm AS base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
# sockets: required by the Pest browser plugin, a dev dependency the image keeps.
RUN install-php-extensions bcmath intl opcache pcntl sockets zip

# Build stage: PHP and Node together, because the Wayfinder Vite plugin runs
# artisan to generate the typed routes during the asset build.
FROM base AS build

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

WORKDIR /app

# Vite inlines this at build time; the image has no .env file.
ENV VITE_APP_NAME="Orbit SaaS"

# Dev dependencies stay: the demo seeders use model factories, which need Faker.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader --no-progress

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY . .
RUN composer dump-autoload --optimize \
    && npm run build \
    && rm -rf node_modules

FROM base

ENV APP_NAME="Orbit SaaS" \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && a2enmod rewrite headers \
    && echo "ServerName localhost" > /etc/apache2/conf-enabled/servername.conf
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-app.ini"
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/entrypoint

WORKDIR /var/www/html
COPY --from=build --chown=www-data:www-data /app /var/www/html

EXPOSE 8080
ENTRYPOINT ["entrypoint"]
CMD ["apache2-foreground"]
