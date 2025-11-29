ARG PHP_VERSION=8.2
FROM php:${PHP_VERSION}-apache

# Install system deps
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libicu-dev \
    libonig-dev \
    libzip-dev \
    zip \
    libxml2-dev \
    && docker-php-ext-install intl mbstring pdo_mysql zip opcache

# Enable apache rewrite
RUN a2enmod rewrite

# Composer
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

# Set workdir
WORKDIR /srv/app

# Copy app (mounted in compose for dev)
# COPY . /srv/app

EXPOSE 80
CMD ["apache2-foreground"]
