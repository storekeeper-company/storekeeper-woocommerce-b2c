#!/bin/bash
set -euo pipefail

echo -n "PHP: "
php -v

echo "Env settings: "
env | grep VERSION
echo -n "Installed Wordpress version: "
wp core version --path=$WORPRESS_ROOT || exit 2

if [ "$COPY_TO_MOUNT" == "1" ]; then
  echo "Making a copy of wordpress in the ./mount easier debugging."
  rsync -rclD --delete --exclude storekeeper-for-woocommerce $WORPRESS_DEV_DIR mount/ || exit 3
else
  echo "COPY_TO_MOUNT is not set to 1. No copy"
fi
cd $STOREKEEPER_PLUGIN_DIR/tests
exec $WORPRESS_DEV_DIR/vendor/bin/phpunit .

