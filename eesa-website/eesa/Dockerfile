FROM php:8.3-apache

# PDO MySQL driver + GD (safe to have for future image handling)
RUN docker-php-ext-install pdo_mysql

# Apache: serve from the project root, enable mod_rewrite (not required yet,
# harmless to have on) and let PHP take over routing that already works via
# plain paths (pages/xxx.php), so no .htaccess rewriting is needed.
RUN a2enmod rewrite

# Composer, for PHPMailer (needed since Render has no local mail server)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Render injects $PORT at runtime; Apache's default vhost listens on 80,
# so we rewrite the listen directive at container start (see CMD below).
ENV APACHE_DOCUMENT_ROOT=/var/www/html

WORKDIR /var/www/html
COPY . /var/www/html

RUN composer install --no-dev --optimize-autoloader --no-interaction

# Uploads must be writable by the web server user
RUN chown -R www-data:www-data /var/www/html/uploads && chmod -R 755 /var/www/html/uploads

EXPOSE 80

# Render sets $PORT; Apache must listen on it.
CMD sh -c "sed -i \"s/80/\${PORT:-80}/g\" /etc/apache2/ports.conf /etc/apache2/sites-enabled/000-default.conf && apache2-foreground"
