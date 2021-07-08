.PHONY: format test

format:
	./vendor/bin/php-cs-fixer fix --config=./.php-cs-fixer.dist.php

test-clean:
	docker-compose -f docker-compose.test.yml down -v

test-pull:
	docker-compose -f docker-compose.test.yml pull

test:
	docker-compose -f docker-compose.test.yml build
	docker-compose -f docker-compose.test.yml up -d db-test web-test
	docker-compose -f docker-compose.test.yml exec -T web-test run-unit-tests
