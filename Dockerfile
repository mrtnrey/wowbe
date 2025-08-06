#################################
# Build CSS & JavaScript assets #
#################################

FROM alpine:3.22 AS assets

ARG VITE_APP_NAME

# Install Node.js, PHP, and required extensions
RUN apk add --no-cache nodejs npm php84 php84-dom php84-exif php84-fileinfo php84-iconv php84-mbstring php84-openssl php84-phar php84-session php84-sodium php84-tokenizer php84-xml php84-xmlwriter
RUN ln -s /usr/bin/php84 /usr/bin/php

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Change working directory
WORKDIR /app

# Install Composer dependencies
COPY composer.json composer.lock .
RUN composer install --no-progress --no-scripts --no-autoloader

# Install Node.js dependencies
COPY package.json package-lock.json .
RUN npm install

# Copy application files
COPY . .

# Finalize Composer autoload
RUN composer dump-autoload --optimize && composer run-script post-autoload-dump

# Write .env.docker file with VITE_APP_NAME coming from environment variable
RUN echo "VITE_APP_NAME=${VITE_APP_NAME}" > .env.docker

# Build CSS & JavaScript assets
RUN npm run build -- --mode docker

###############
# Application #
###############

FROM dunglas/frankenphp:php8.4-alpine AS laravel

# Install required PHP extensions
RUN install-php-extensions exif intl pcntl pdo_pgsql

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Change working directory
WORKDIR /app

# Install Composer dependencies
COPY composer.json composer.lock .
RUN composer install --no-progress --no-dev --no-scripts --no-autoloader

# Copy application files
COPY . .
# Copy built assets from the build stage
COPY --from=assets /app/public/build /app/public/build

# Finalize Composer autoload
RUN composer dump-autoload --optimize && composer run-script post-autoload-dump

# Change ownership to `nobody` user and group
RUN chown -R nobody:nobody /app

# Set capabilities for `frankenphp` binary to allow binding to low ports
# This is necessary for running on ports < 1024, such as port 80 and 443
RUN setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp;

# Run as `nobody` user
USER nobody

# Start the application
ENTRYPOINT ["php", "artisan", "octane:frankenphp"]

HEALTHCHECK --interval=10s --timeout=5s --retries=3 \
    CMD ["php", "artisan", "octane:status"]
