.PHONY: phpstan fix

.DEFAULT_GOAL := help

## help: Show help
help:
	@echo "Available commands:"
	@echo "  make phpstan       - Run PHPStan static analysis"
	@echo "  make cs-fix        - Fix code style with PHP-CS-Fixer"

## phpstan: Run PHPStan static analysis
phpstan:
	.tools/vendor/bin/phpstan analyse -l 6 src tests

## cs-fix: Fix code style with PHP-CS-Fixer
fix:
	.tools/vendor/bin/php-cs-fixer fix --allow-risky=yes

