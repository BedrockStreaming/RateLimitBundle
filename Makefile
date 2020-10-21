SHELL=bash -o pipefail
SOURCE_DIR = $(shell pwd)
BIN_DIR = ${SOURCE_DIR}/bin
COMPOSER = composer

define printSection
	@printf "\033[36m\n==================================================\n\033[0m"
	@printf "\033[36m $1 \033[0m"
	@printf "\033[36m\n==================================================\n\033[0m"
endef

.PHONY: all
all: install quality test

.PHONY: ci
ci: quality test

.PHONY: install
install: clean-vendor composer-install

.PHONY: quality
quality: cs-ci phpstan

.PHONY: test
test: phpunit

### DEPENDENCIES ###

.PHONY: clean-vendor
clean-vendor:
	$(call printSection,DEPENDENCIES clean)
	rm -rf ${SOURCE_DIR}/vendor

.PHONY: composer-install
composer-install: ${SOURCE_DIR}/vendor/composer/installed.json

${SOURCE_DIR}/vendor/composer/installed.json:
	$(call printSection,DEPENDENCIES install)
	$(COMPOSER) --no-interaction install --ansi --no-progress --prefer-dist

### TEST ###

.PHONY: phpunit
phpunit:
	$(call printSection,TEST phpunit)
	${BIN_DIR}/phpunit

### QUALITY ###

.PHONY: phpstan
phpstan:
	$(call printSection,QUALITY phpstan)
	${BIN_DIR}/phpstan analyse --memory-limit=1G

.PHONY: cs-ci
cs-ci:
	$(call printSection,QUALITY php-cs-fixer check)
	${BIN_DIR}/php-cs-fixer fix --ansi --dry-run --using-cache=no --verbose

.PHONY: cs-fix
cs-fix:
	$(call printSection,QUALITY php-cs-fixer fix)
	${BIN_DIR}/php-cs-fixer fix

