ARG PHP_VERSION=8.3

FROM composer:2 AS composer
FROM php:${PHP_VERSION}-cli

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install --no-install-recommends -y \
    git \
    unzip \
    libicu-dev \
    libzip-dev \
  && docker-php-ext-configure intl \
  && docker-php-ext-install intl zip \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html
