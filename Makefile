.PHONY: format test
USER_ID := $(shell id -u)
GROUP_ID := $(shell id -g)

format:
	./vendor/bin/php-cs-fixer fix --config=./.php-cs-fixer.dist.php

test-clean:
	docker-compose -f docker-compose.test.yml down -v

test-pull:
	docker-compose -f docker-compose.test.yml pull

test:
	USER_ID=${USER_ID} GROUP_ID=${GROUP_ID} docker-compose -f docker-compose.test.yml build
	USER_ID=${USER_ID} GROUP_ID=${GROUP_ID} docker-compose -f docker-compose.test.yml up -d db-test web-test
	USER_ID=${USER_ID} GROUP_ID=${GROUP_ID} docker-compose -f docker-compose.test.yml exec -T web-test run-unit-tests
