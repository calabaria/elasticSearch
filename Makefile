# —— Inspired by ———————————————————————————————————————————————————————————————
# http://fabien.potencier.org/symfony4-best-practices.html
# https://speakerdeck.com/mykiwi/outils-pour-ameliorer-la-vie-des-developpeurs-symfony?slide=47
# https://blog.theodo.fr/2018/05/why-you-need-a-makefile-on-your-project/
# Setup ————————————————————————————————————————————————————————————————————————

# Parameters
SHELL         = bash
PROJECT       = symfony-elastic
GIT_AUTHOR    = RADOUANE
HTTP_PORT     = 8000

# Executables
EXEC_PHP      = php
COMPOSER      = composer
REDIS         = redis-cli
GIT           = git
YARN          = yarn
NPX           = npx

# Alias
SYMFONY       = $(EXEC_PHP) bin/console
# if your using Docker you can replace "with: docker-composer exec myphpcontainer $(EXEC_PHP) bin/console"

# Executables: vendors
PHPUNIT       = ./vendor/bin/simple-phpunit
# PHPSTAN       = ./vendor/bin/phpstan
# PHP_CS_FIXER  = ./vendor/bin/php-cs-fixer
# CODESNIFFER   = ./vendor/squizlabs/php_codesniffer/bin/phpcs

# Executables: local only
SYMFONY_BIN   = symfony
BREW          = brew
DOCKER        = docker
DOCKER_COMP   = docker-compose

# Executables: prod only
CERTBOT       = certbot

# Misc
.DEFAULT_GOAL = help
.PHONY       =  # Not needed here, but you can put your all your targets to be sure
                # there is no name conflict between your files and your targets.

## —— 🐝 The Strangebuzz Symfony Makefile 🐝 ———————————————————————————————————
help: ## Outputs this help screen
	@grep -E '(^[a-zA-Z0-9_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

## —— Composer 🧙‍♂️ ————————————————————————————————————————————————————————————
install: composer.lock ## Install vendors according to the current composer.lock file
	$(COMPOSER) install --no-progress --prefer-dist --optimize-autoloader

## —— PHP 🐘 (macOS with brew) —————————————————————————————————————————————————
php-upgrade: ## Upgrade PHP to the last version
	$(BREW) upgrade php

php-set-7-2: ## Set php 7.2 as the current PHP version
	$(BREW) unlink php@7.3 && brew unlink php@7.4
	$(BREW) link php@7.2 --force

php-set-7-3: ## Set php 7.3 as the current PHP version
	$(BREW) unlink php@7.2 && brew unlink php@7.4
	$(BREW) link php@7.3 --force

php-set-7-4: ## Set php 7.4 as the current PHP version
	$(BREW) unlink php@7.2 && brew unlink php@7.3
	$(BREW) link php@7.4 --force

## —— Symfony 🎵 ———————————————————————————————————————————————————————————————
sf: ## List all Symfony commands
	$(SYMFONY)

cc: ## Clear the cache. DID YOU CLEAR YOUR CACHE????
	$(SYMFONY) c:c

warmup: ## Warmup the cache
	$(SYMFONY) cache:warmup

fix-perms: ## Fix permissions of all var files
	chmod -R 777 var/*

assets: purge ## Install the assets with symlinks in the public folder
	$(SYMFONY) assets:install public/ --symlink --relative

purge: ## Purge cache and logs
	rm -rf var/cache/* var/logs/*

## —— Symfony binary 💻 ————————————————————————————————————————————————————————
cert-install: ## Install the local HTTPS certificates
	$(SYMFONY_BIN) server:ca:install

serve: ## Serve the application with HTTPS support
	$(SYMFONY_BIN) serve --daemon --port=$(HTTP_PORT)

unserve: ## Stop the webserver
	$(SYMFONY_BIN) server:stop

# Snippet L88+8 => templates/blog/posts/_64.html.twig

## —— Elasticsearch 🔎 —————————————————————————————————————————————————————————
populate: ## Reset and populate the Elasticsearch index
	$(SYMFONY) fos:elastica:reset
	$(SYMFONY) fos:elastica:populate --index=app
	$(SYMFONY) symfony-elastic:populate

# Snippet L100+4 => templates/blog/posts/_51.html.twig

list-index: ## List all indexes on the cluster
	curl http://localhost:9209/_cat/indices?v

delete-index: ## Delete a given index (replace "index" by the index name to delete)
	curl -X DELETE "localhost:9209/index?pretty"

## —— Docker 🐳 ————————————————————————————————————————————————————————————————
up: ## Start the docker hub (MySQL,redis,adminer,elasticsearch,head,Kibana)
	$(DOCKER_COMP) up -d

docker-build: ## UP+rebuild the application image
	$(DOCKER_COMP) up -d --build

down: ## Stop the docker hub
	$(DOCKER_COMP) down --remove-orphans

wait-for-mysql: ## Wait for MySQL to be ready
	bin/wait-for-mysql.sh

wait-for-elasticsearch: ## Wait for Elasticsearch to be ready
	bin/wait-for-elasticsearch.sh

bash: ## Connect to the application container
	$(DOCKER) container exec -it sb-app bash

## —— Project 🐝 ———————————————————————————————————————————————————————————————
start: up wait-for-mysql load-fixtures wait-for-elasticsearch populate serve ## Start docker, load fixtures, populate the Elasticsearch index and start the webserver

reload: load-fixtures populate ## Load fixtures and repopulate the Elasticserch index

stop: down unserve ## Stop docker and the Symfony binary server

cc-redis: ## Flush all Redis cache
	$(REDIS) -p 6389 flushall

commands: ## Display all commands in the project namespace
	$(SYMFONY) list $(PROJECT)

load-fixtures: ## Build the DB, control the schema validity, load fixtures and check the migration status
	$(SYMFONY) doctrine:cache:clear-metadata
	$(SYMFONY) doctrine:database:create --if-not-exists
	$(SYMFONY) doctrine:schema:drop --force
	$(SYMFONY) doctrine:schema:create
	$(SYMFONY) doctrine:schema:validate
	$(SYMFONY) hautelook:fixtures:load --no-interaction -vv # doctrine:fixtures:load -n

init-snippet: ## Initialize a new snippet
	$(SYMFONY) $(PROJECT):init-snippet