
.PHONY: default
default: build

.PHONY: build
build: vendor/autoload.php

.PHONY: fix
fix: vendor/autoload.php
	php vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php --diff

.PHONY: lint
lint: vendor/autoload.php
	php vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php --diff --dry-run

vendor/autoload.php: composer.json composer.lock
	composer install --prefer-dist --no-scripts --no-plugins
	touch vendor/autoload.php
