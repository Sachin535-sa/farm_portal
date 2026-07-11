FROM php:8.2-apache

# Install and enable mysqli extension for MySQL database connection
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Copy the application source code to the Apache public directory
COPY . /var/www/html/

# Configure permissions for upload folders to make them writable
RUN mkdir -p /var/www/html/uploads && chmod -R 777 /var/www/html/uploads

# Enable Apache mod_rewrite (useful if we add clean URLs later)
RUN a2enmod rewrite

# Expose port 80 (default HTTP port for Render Web Services)
EXPOSE 80
