# Use PHP 8.4 FPM image as a base
FROM php:8.4-fpm

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libxml2-dev \
    && docker-php-ext-install pdo_mysql mysqli

# Set working directory inside the container
WORKDIR /var/www/html

# Copy project files into the container (replace "." with the path to your project files)
COPY . /var/www/html

# Expose port 8080 (or any port you wish)
EXPOSE 8080

# Command to run PHP-FPM server
CMD ["php-fpm"]
