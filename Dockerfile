FROM php:8.2-apache

# Install system dependencies & PostgreSQL development libraries
RUN apt-get update && apt-get install -y \
    libpq-dev \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install and configure PHP extensions for both PostgreSQL and MySQL
RUN docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo pdo_pgsql pgsql mysqli pdo_mysql

# Enable Apache rewrite module
RUN a2enmod rewrite

# Configure Apache port dynamically for Render ($PORT)
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Set proper permissions for Apache user
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose default port
EXPOSE 80 10000

# Start Apache in foreground with environment variable substitution
CMD ["apache2-foreground"]
