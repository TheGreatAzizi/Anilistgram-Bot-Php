# Use an official PHP image with Apache or Nginx/FPM (Apache is simpler for basic setups)
FROM php:8.2-apache

# Install any necessary PHP extensions (optional, but good practice if you need them)
# RUN docker-php-ext-install curl json

# Set the working directory in the container
WORKDIR /var/www/html

# Copy your PHP files into the container's web directory
COPY . /var/www/html/

# Expose port 80 for the web server
EXPOSE 80

# Apache is already configured to serve index.php by default.
# No CMD needed, as Apache takes care of it.
