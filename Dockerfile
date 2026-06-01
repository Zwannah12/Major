FROM php:8.2-apache

# Enable Apache rewrite (important for many PHP apps)
RUN a2enmod rewrite

# Install PHP extensions (common for web apps + MySQL)
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy your project into Apache web root
COPY . /var/www/html/

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80