FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libsmbclient-dev \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN pecl install smbclient \
    && docker-php-ext-enable smbclient

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Allow composer to run as superuser
ENV COMPOSER_ALLOW_SUPERUSER=1

CMD ["php", "-a"]
