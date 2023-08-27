#!/bin/bash
set -euo pipefail

echo "Env settings: "
env | grep VERSION
echo -n "Installed Wordpress version: "
wp core version --path=$WORPRESS_ROOT || exit 2

echo "Making a copy for easier debugging."
rsync -r --delete --exclude storekeeper-for-woocommerce $WORPRESS_DEV_DIR mount/ || exit 3

cd $STOREKEEPER_PLUGIN_DIR/tests/
exec $WORPRESS_DEV_DIR/vendor/bin/phpunit  --bootstrap $STOREKEEPER_PLUGIN_DIR/tests/bootstrap.php .
