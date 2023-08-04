FROM php:latest

# Update package lists and install required dependencies
RUN apt-get update && apt-get install -y wget libzip-dev zip

# Install the PHP extension zip
RUN docker-php-ext-install zip

# Install the PHP extension zip
RUN docker-php-ext-install zip

# Install the AWS SDK for PHP using Composer
RUN apt-get install -y git
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer require aws/aws-sdk-php

# Create the output directory
RUN mkdir /output

# Set read and write permissions for the /output directory and its contents
RUN chmod -R 777 /output

# Copy the conversion script
COPY convert.php /convert.php

# Create a new user named "appuser" with UID/GID 1000
RUN useradd -m -u 1000 -U appuser

# Set the ownership of the /home/appuser directory to "appuser"
RUN chown -R appuser:appuser /home/appuser

# Switch to the non-root user
USER 1000

# Run the conversion script when the container starts
CMD ["/usr/local/bin/php", "/convert.php"]
