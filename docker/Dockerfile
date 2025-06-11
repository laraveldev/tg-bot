FROM php:8.4-fpm

# System dependencies
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    zip unzip git curl \
    libpq-dev \
    libonig-dev \
    && docker-php-ext-install pdo pdo_pgsql bcmath gd zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy existing app files
COPY . /var/www

# Install Laravel if not installed yet (optional, skip if already present)
# RUN composer create-project laravel/laravel . "^12.0"

# Install Telegraph
RUN composer require defstudio/telegraph

# Set permissions
RUN chown -R www-data:www-data /var/www

EXPOSE 9000
CMD ["php-fpm"]