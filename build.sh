#!/bin/bash

# Script to update composer dependencies without updating getkirby/cms and getkirby/composer-installer

echo "Backing up composer.json..."
cp composer.json composer.json.backup

echo "Temporarily removing getkirby package from composer.json..."
sed -i '' '/getkirby\/cms/d' composer.json

echo "Running composer update..."
composer update --no-dev

echo "Restoring composer.json..."
cp composer.json.backup composer.json
rm composer.json.backup

echo "Done! composer.json has been restored."
