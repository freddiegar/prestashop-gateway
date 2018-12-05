#!/bin/sh

CONTAINER_PS=plugin_ps_prestashop
CONTAINER_DB=plugin_ps_database
CURRENT_FOLDER=$(shell pwd)
UID=$(shell id -u)
APACHE_USER=www-data
MODULE_NAME=placetopaypayment

# Persistence commands

.PHONY: config
config: restore-override
	@docker-compose config

.PHONY: up
up: restore-override
	@docker-compose up -d

.PHONY: down
down: restore-override
	@docker-compose down -v

.PHONY: restart
restart: dev-down restore-override down up

.PHONY: rebuild
rebuild: dev-down restore-override down
	@docker-compose up -d --build
	@make install

.PHONY: install
install: dev-down restore-override down up set-acl composer-update
	@echo "That is all!"

# Development commands

.PHONY: dev-config
dev-config: move-override
	@docker-compose config

.PHONY: dev-up
dev-up: move-override
	@docker-compose up -d

.PHONY: dev-down
dev-down: move-override
	@docker-compose down -v

.PHONY: dev-restart
dev-restart: down move-override dev-down dev-up

.PHONY: dev-rebuild
dev-rebuild: down move-override dev-down
	@docker-compose up -d --build
	@make dev-install

.PHONY: dev-install
dev-install: down move-override dev-down dev-up set-acl composer-update
	@echo "That is all!"

# Generic commands

.PHONY: set-acl
set-acl: set-acl-unix

.PHONY: set-acl-unix
set-acl-unix:
	@echo "Verifing ACL folder"
	@if [ -z "$(shell getfacl -acp $(CURRENT_FOLDER) | grep $(APACHE_USER))" ]; \
	then \
		sudo setfacl -dR -m u:$(APACHE_USER):rwX -m u:`whoami`:rwX $(CURRENT_FOLDER); \
		sudo setfacl -R -m u:$(APACHE_USER):rwX -m u:`whoami`:rwX $(CURRENT_FOLDER); \
	fi;

.PHONY: start
start: start-database start-prestashop

.PHONY: stop
stop: stop-prestashop stop-database

.PHONY: start-prestashop
start-prestashop:
	@docker container start $(CONTAINER_PS)

.PHONY: stop-prestashop
stop-prestashop:
	@docker container stop $(CONTAINER_PS)

.PHONY: start-database
start-database:
	@docker container start $(CONTAINER_DB)

.PHONY: stop-database
stop-database:
	@docker container stop $(CONTAINER_DB)

.PHONY: bash-prestashop
bash-prestashop:
	@docker exec -u root -it $(CONTAINER_PS) bash

.PHONY: bash-database
bash-database:
	@docker exec -u root -it $(CONTAINER_DB) bash

.PHONY: logs-prestashop
logs-prestashop:
	@docker logs $(CONTAINER_PS) -f

.PHONY: logs-database
logs-database:
	@docker logs $(CONTAINER_DB) -f

.PHONY: composer-update
composer-update:
	@composer update

# Utils commands

.PHONY: move-override
move-override:
	@if [ -e docker-compose.override.yml ]; \
	then \
		mv docker-compose.override.yml docker-compose.backup.yml; \
	fi;

.PHONY: restore-override
restore-override:
	@if [ -e docker-compose.backup.yml ]; \
	then \
		mv docker-compose.backup.yml docker-compose.override.yml; \
	fi;

.PHONY: compile
compile:
	$(eval MODULE_NAME_VR=$(MODULE_NAME)$(PLUGIN_VERSION))
	@touch ~/Downloads/placetopaypayment_test \
        && sudo rm -Rf ~/Downloads/placetopaypayment* \
        && sudo cp $(CURRENT_FOLDER) ~/Downloads/placetopaypayment -R \
        && sudo find ~/Downloads/placetopaypayment/ -type d -name ".git*" -exec rm -Rf {} + \
        && sudo find ~/Downloads/placetopaypayment/ -type d -name "squizlabs" -exec rm -Rf {} + \
        && sudo rm -Rf ~/Downloads/placetopaypayment/.git* \
        && sudo rm -Rf ~/Downloads/placetopaypayment/.idea \
        && sudo rm -Rf ~/Downloads/placetopaypayment/config* \
        && sudo rm -Rf ~/Downloads/placetopaypayment/Dockerfile \
        && sudo rm -Rf ~/Downloads/placetopaypayment/Makefile \
        && sudo rm -Rf ~/Downloads/placetopaypayment/.env* \
        && sudo rm -Rf ~/Downloads/placetopaypayment/docker* \
        && sudo rm -Rf ~/Downloads/placetopaypayment/composer.* \
        && sudo rm -Rf ~/Downloads/placetopaypayment/*.md \
        && sudo rm -Rf ~/Downloads/placetopaypayment/LICENSE \
        && cd ~/Downloads \
        && sudo zip -r -q -o $(MODULE_NAME_VR).zip placetopaypayment \
        && sudo chown $(UID):$(UID) $(MODULE_NAME_VR).zip \
        && sudo chmod 644 $(MODULE_NAME_VR).zip \
        && sudo rm -Rf ~/Downloads/placetopaypayment
	@echo "Compile file complete: ~/Downloads/$(MODULE_NAME_VR).zip"

.PHONY: download
download:
	sudo mkdir -p /var/www/prestashop/$(PRESTASHOP_VERSION)
	sudo chown www-data:www-data -Rf /var/www/prestashop/$(PRESTASHOP_VERSION)
	wget https://download.prestashop.com/download/releases/prestashop_$(PRESTASHOP_VERSION).zip -O ~/Downloads/$(PRESTASHOP_VERSION).zip \
        && sudo unzip ~/Downloads/$(PRESTASHOP_VERSION).zip -d /var/www/prestashop/$(PRESTASHOP_VERSION)
	@echo "Go to: http://localhost:8787/$(PRESTASHOP_VERSION)/"