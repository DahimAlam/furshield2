# Use official PHP + Apache image
FROM php:8.2-apache

# Install PHP extensions (mysqli for MySQL)
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache rewrite module (for clean URLs)
RUN a2enmod rewrite

# Copy project files into container
COPY . /var/www/html/

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
