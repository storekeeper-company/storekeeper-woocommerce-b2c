## ---- Parameters ----------------------------------------
LOKALISE_PROJECT_ID=73695952636a8c7112e274.93369648
TMP_DIR:=$(shell mktemp -d -t skforwc-mk-XXXX)

.PHONY: format test test-build test-clean test-only test-bash

## ---- Codesniffer format ----------------------------------------
format:
	./vendor/bin/php-cs-fixer fix --config=./.php-cs-fixer.dist.php

## ---- Unit testing ----------------------------------------
test-clean:
	docker compose down --volumes db-test test

test-build:
	docker compose build test

test-only:
	docker compose run --rm test .

test: test-build
	docker compose run --rm test .

test-bash: test-build
	docker compose run --rm --entrypoint=/bin/bash test

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
