FROM php:8.3-fpm

# 1. Switch to a closer mirror (Singapore) to speed up downloads in the Philippines
# 2. Install system dependencies
RUN sed -i 's/deb.debian.org/mirror.nus.edu.sg/g' /etc/apt/sources.list.d/debian.sources || \
    sed -i 's/deb.debian.org/mirror.nus.edu.sg/g' /etc/apt/sources.list && \
    apt-get update && apt-get install -y \
    git \
    curl \
    libpq-dev \
    libgd-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install gd pdo pdo_pgsql zip \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# --- OPTIMIZATION START ---
# Copy ONLY composer files first. This allows Docker to cache the 'composer install' 
# layer. As long as your composer.json doesn't change, your server skips this step.
COPY composer.json composer.lock* ./

RUN if [ -f "composer.json" ]; then \
        composer install --no-scripts --no-autoloader --prefer-dist --no-interaction; \
    fi
# --- OPTIMIZATION END ---

# Copy the rest of the application
COPY . .

# Finalize composer and permissions
RUN if [ -f "composer.json" ]; then \
        composer dump-autoload --optimize; \
    fi

RUN if [ -d "storage" ]; then \
        chown -R www-data:www-data storage bootstrap/cache && \
        chmod -R 775 storage bootstrap/cache; \
    fi

# PHP Configuration for Aktiv (Cover + Gallery uploads)
RUN { \
        echo 'upload_max_filesize=12M'; \
        echo 'post_max_size=64M'; \
        echo 'max_file_uploads=20'; \
    } > /usr/local/etc/php/conf.d/uploads.ini

EXPOSE 9000

CMD ["php-fpm"]