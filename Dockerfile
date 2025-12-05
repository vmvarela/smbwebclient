FROM dunglas/frankenphp:latest-php8.3

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libsmbclient-dev \
    smbclient \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer globally
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm composer-setup.php

# Install PHP extensions
RUN install-php-extensions smbclient

# Set working directory
WORKDIR /app

# Copy application files
COPY . /app

# Install composer dependencies
RUN composer install --no-dev --optimize-autoloader

# Create cache directory
RUN mkdir -p /app/cache && chown -R www-data:www-data /app/cache

# Expose port
EXPOSE 80

# Start FrankenPHP
CMD ["frankenphp", "run", "--config", "/app/Caddyfile"]
