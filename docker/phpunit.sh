#!/bin/bash
set -euo pipefail

PHPUNIT_BIN=/var/www/html/wp-content/plugins/storekeeper-for-woocommerce/vendor/bin/phpunit
if [ "$EUID" -eq 0 ]
then sudo -u www-data $PHPUNIT_BIN "$@"
else $PHPUNIT_BIN "$@"
fi
