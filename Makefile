## ---- Parameters ----------------------------------------
LOKALISE_PROJECT_ID=73695952636a8c7112e274.93369648

.PHONY: format test test-build test-clean test-only test-bash

## ---- Codesniffer format ----------------------------------------
format:
	./vendor/bin/php-cs-fixer fix --config=./.php-cs-fixer.dist.php

## ---- Development build ----------------------------------------

dev-prepare-mount:
	mkdir -p mount/wordpress/wp-content/plugins

build-bash:
	docker compose -f docker-compose.build.yml run --rm build bash

dev-clean:
	docker compose down --volumes dev db

dev-build: dev-prepare-mount
	docker compose build dev

dev-bash: dev-build
	docker compose run --rm dev bash

## ---- Unit testing ----------------------------------------
test-prepare-mount:
	mkdir -p mount/wordpress-develop-tests

test-clean:
	docker compose down --volumes db-test test

test-build:
	docker compose build test

test-only: test-prepare-mount
	docker compose run --rm test run-phpunit

test: test-build test-only

test-bash: test-build test-prepare-mount
	docker compose run --rm test bash

## ---- Translations ----------------------------------------
extract-translations: dev-prepare-mount
	docker compose run --rm dev php /var/www/html/wordpress/wp-content/plugins/storekeeper-for-woocommerce/dev-tools/make-pot.php

pull-translations: dev-prepare-mount
	cd ./i18n/ && ../dev-tools/i18n/lokalise2 --token=${LOKALISE_TOKEN} --project-id=${LOKALISE_PROJECT_ID} \
		file download \
		--format=po \
		--export-empty-as=skip \
		--bundle-structure "storekeeper-for-woocommerce-%LANG_ISO%.po" \
		--original-filenames=false &&\
	docker compose run --rm dev bash /var/www/html/wordpress/wp-content/plugins/storekeeper-for-woocommerce/docker/translate-to-machine-object.sh
	echo "OK"

push-translations:
	cd ./i18n/ && ../dev-tools/i18n/lokalise2 --token=${LOKALISE_TOKEN} --project-id=${LOKALISE_PROJECT_ID} \
		file upload \
		--cleanup-mode \
		--file storekeeper-woocommerce-b2c.pot \
		--lang-iso en_US \
		--include-path \
		--poll \
		--slashn-to-linebreak &&\
	echo "OK"
