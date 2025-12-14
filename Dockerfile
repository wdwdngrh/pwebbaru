FROM php:8.4-apache

# Install necessary PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql mysqli

# Disable other MPMs (e.g., mpm_event and mpm_worker)
RUN a2dismod mpm_event mpm_worker

# Enable mpm_prefork
RUN a2enmod mpm_prefork

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory for Apache
WORKDIR /var/www/html

# Copy application files into the container
COPY . /var/www/html/

# Expose port 80
EXPOSE 8080

# Start Apache in the foreground
CMD ["apache2-foreground"]
