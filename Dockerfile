FROM php:8.2-apache

# Enable required PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY backend/ .

# Create storage directory and set permissions
RUN mkdir -p storage/product-images \
    && chown -R www-data:www-data storage \
    && chmod -R 755 storage

# Apache configuration - allow .htaccess overrides
RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/app.conf \
    && a2enconf app

# PHP configuration for uploads
RUN echo "upload_max_filesize = 5M\n\
post_max_size = 10M\n\
memory_limit = 128M" > /usr/local/etc/php/conf.d/uploads.ini

EXPOSE 80
