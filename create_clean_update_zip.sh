#!/bin/bash
# Create clean update package for BWG Instagram Feed v2.0.0

# Clean up old files
rm -rf /tmp/update-pkg
rm -f /var/www/html/bwg-instagram-feed-2.0.0.zip

# Create temp directory structure
mkdir -p /tmp/update-pkg/bwg-instagram-feed

# Copy only essential plugin files
cp /var/www/html/wp-content/plugins/bwg-instagram-feed/bwg-instagram-feed.php /tmp/update-pkg/bwg-instagram-feed/
cp /var/www/html/wp-content/plugins/bwg-instagram-feed/uninstall.php /tmp/update-pkg/bwg-instagram-feed/
cp -r /var/www/html/wp-content/plugins/bwg-instagram-feed/includes /tmp/update-pkg/bwg-instagram-feed/
cp -r /var/www/html/wp-content/plugins/bwg-instagram-feed/assets /tmp/update-pkg/bwg-instagram-feed/
cp -r /var/www/html/wp-content/plugins/bwg-instagram-feed/templates /tmp/update-pkg/bwg-instagram-feed/
cp -r /var/www/html/wp-content/plugins/bwg-instagram-feed/languages /tmp/update-pkg/bwg-instagram-feed/

# Update version in main plugin file
sed -i "s/Version: 1.0.0/Version: 2.0.0/" /tmp/update-pkg/bwg-instagram-feed/bwg-instagram-feed.php
sed -i "s/BWG_IGF_VERSION', '1.0.2'/BWG_IGF_VERSION', '2.0.0'/" /tmp/update-pkg/bwg-instagram-feed/bwg-instagram-feed.php

# Create ZIP file from /tmp/update-pkg directory
cd /tmp/update-pkg
zip -r /var/www/html/bwg-instagram-feed-2.0.0.zip bwg-instagram-feed

# Show file size
ls -la /var/www/html/bwg-instagram-feed-2.0.0.zip

echo "Clean ZIP created successfully!"
