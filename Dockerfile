# Use official PHP Apache image
FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable mod_rewrite (common in PHP apps)
RUN a2enmod rewrite

# Copy all your PHP files to Apache's root directory
COPY . /var/www/html/

# Set correct ownership and permissions
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

# Expose default Apache port
EXPOSE 80
