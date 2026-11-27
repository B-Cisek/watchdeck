.PHONY: phpstan fix up down test

.DEFAULT_GOAL := help

## help: Show help
help:
	@echo "Available commands:"
	@echo "  make phpstan       - Run PHPStan static analysis"
	@echo "  make cs-fix        - Fix code style with PHP-CS-Fixer"
	@echo "  make up       		- Spin up database"
	@echo "  make down       	- Down database"
	@echo "  make test       	- Run tests"

## phpstan: Run PHPStan static analysis
phpstan:
	.tools/vendor/bin/phpstan analyse -l 6 src tests

## cs-fix: Fix code style with PHP-CS-Fixer
fix:
	.tools/vendor/bin/php-cs-fixer fix --allow-risky=yes
up:
	podman compose up -d
down:
	podman compose down
test:
	vendor/bin/phpunit


