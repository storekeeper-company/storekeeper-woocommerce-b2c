## ---- Parameters ----------------------------------------
LOKALISE_PROJECT_ID=73695952636a8c7112e274.93369648
TMP_DIR:=$(shell mktemp -d -t skforwc-mk-XXXX)

.PHONY: format test test-build test-clean test-only test-bash

## ---- Codesniffer format ----------------------------------------
format:
	./vendor/bin/php-cs-fixer fix --config=./.php-cs-fixer.dist.php


## ---- dev build ----------------------------------------
dev-clean:
	docker compose down --volumes web db

dev-build:
	docker compose build dev

dev-bash:
	docker compose run --rm  dev bash

## ---- Unit testing ----------------------------------------
test-clean:
	docker compose down --volumes db-test test

test-build:
	docker compose build test

test-only:
	docker compose run --rm test run-phpunit

test: test-build test-only

test-bash: test-build
	docker compose run --rm test bash

## ---- Translations ----------------------------------------
extract-translations:
	docker-compose up --build -d web
	docker-compose exec -T web bash /bin/extract-translations

pull-translations:
	dev-tools/lokalise2 --token=${LOKALISE_TOKEN} --project-id=${LOKALISE_PROJECT_ID} \
		file download \
		--format=po \
		--export-empty-as=skip \
		--bundle-structure "i18n/storekeeper-for-woocommerce-%LANG_ISO%.po" \
		--original-filenames=false &&\
	echo "OK"
	docker-compose up --build -d web
	docker-compose exec -T web translate-to-machine-object

push-translations:
	cd ./i18n/ && lokalise2 --token=${LOKALISE_TOKEN} --project-id=${LOKALISE_PROJECT_ID} \
		file upload \
		--file storekeeper-woocommerce-b2c.pot \
		--lang-iso en_US \
		--include-path \
		--slashn-to-linebreak &&\
	echo "OK"
