#!/bin/bash
# Create update package for BWG Instagram Feed v2.0.0

# Create temp directory
mkdir -p /tmp/update-pkg

# Copy plugin files (only essential ones)
cp -r /var/www/html/wp-content/plugins/bwg-instagram-feed/bwg-instagram-feed.php /tmp/update-pkg/
cp -r /var/www/html/wp-content/plugins/bwg-instagram-feed/uninstall.php /tmp/update-pkg/
cp -r /var/www/html/wp-content/plugins/bwg-instagram-feed/includes /tmp/update-pkg/
cp -r /var/www/html/wp-content/plugins/bwg-instagram-feed/assets /tmp/update-pkg/
cp -r /var/www/html/wp-content/plugins/bwg-instagram-feed/templates /tmp/update-pkg/
cp -r /var/www/html/wp-content/plugins/bwg-instagram-feed/languages /tmp/update-pkg/

# Update version in main plugin file
sed -i "s/Version: 1.0.0/Version: 2.0.0/" /tmp/update-pkg/bwg-instagram-feed.php
sed -i "s/BWG_IGF_VERSION', '1.0.2'/BWG_IGF_VERSION', '2.0.0'/" /tmp/update-pkg/bwg-instagram-feed.php

# Create ZIP file
mkdir -p /tmp/update-pkg/bwg-instagram-feed
mv /tmp/update-pkg/bwg-instagram-feed.php /tmp/update-pkg/bwg-instagram-feed/
mv /tmp/update-pkg/uninstall.php /tmp/update-pkg/bwg-instagram-feed/
mv /tmp/update-pkg/includes /tmp/update-pkg/bwg-instagram-feed/
mv /tmp/update-pkg/assets /tmp/update-pkg/bwg-instagram-feed/
mv /tmp/update-pkg/templates /tmp/update-pkg/bwg-instagram-feed/
mv /tmp/update-pkg/languages /tmp/update-pkg/bwg-instagram-feed/

cd /tmp/update-pkg
zip -r /var/www/html/bwg-instagram-feed-2.0.0.zip bwg-instagram-feed

echo "ZIP created at /var/www/html/bwg-instagram-feed-2.0.0.zip"
