# =====================================================================
# FloodRelief DSS - Production Dockerfile for Render.com
# PHP 8.2 with Apache & PostgreSQL (pdo_pgsql) Support
# =====================================================================

FROM php:8.2-apache

# Set working directory
WORKDIR /var/www/html

# Install PostgreSQL client headers, libraries, and utilities
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    ca-certificates \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules (rewrite, headers)
RUN a2enmod rewrite headers

# Configure Apache virtual host with AllowOverride All
RUN { \
    echo '<Directory /var/www/html/>'; \
    echo '    Options -Indexes +FollowSymLinks'; \
    echo '    AllowOverride All'; \
    echo '    Require all granted'; \
    echo '</Directory>'; \
} > /etc/apache2/conf-available/floodrelief.conf \
    && a2enconf floodrelief

# Copy application files into web root
COPY . /var/www/html/

# Ensure runtime directories exist with proper permissions for www-data
RUN mkdir -p /var/www/html/storage/logs \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/storage

# Expose standard port
EXPOSE 80

# Run Apache in foreground with correct dynamic port mapping
CMD bash -c "PORT=\${PORT:-80}; sed -i \"s/80/\$PORT/g\" /etc/apache2/ports.conf /etc/apache2/sites-available/*.conf && apache2-foreground"