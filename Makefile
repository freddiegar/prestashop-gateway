CONTAINER_PS=plugin_ps_prestashop
CONTAINER_DB=plugin_ps_database
FOLDER_PATH=/var/www/html/modules/prestashop-gateway
CURRENT_FOLDER=$(shell pwd)

.PHONY: config
config:
	docker-compose config

.PHONY: up
up:
	docker-compose up -d

.PHONY: down
down:
	docker-compose down -v

.PHONY: restart
restart: down up

.PHONY: rebuild
rebuild: down
	docker-compose up -d --build
	make install

.PHONY: bash-prestashop
bash-prestashop:
	docker exec -u www-data -it $(CONTAINER_PS) bash

.PHONY: bash-database
bash-database:
	docker exec -u root -it $(CONTAINER_DB) bash

.PHONY: logs-prestashop
logs-prestashop:
	docker logs $(CONTAINER_PS) -f

.PHONY: logs-database
logs-database:
	docker logs $(CONTAINER_DB) -f

.PHONY: install
install: down up
	sudo setfacl -dR -m u:www-data:rwX -m u:`whoami`:rwX `pwd`
	sudo setfacl -R -m u:www-data:rwX -m u:`whoami`:rwX `pwd`
	composer update
	make logs-prestashop
	@echo "That is all!"

.PHONY: compile
compile:
	touch ~/Downloads/prestashop-gateway_test \
        && sudo rm -Rf ~/Downloads/prestashop-gateway* \
        && sudo cp $(CURRENT_FOLDER) ~/Downloads/. -R \
        && sudo find ~/Downloads/prestashop-gateway/ -type d -name ".git*" -exec rm -Rf {} + \
        && sudo rm -Rf ~/Downloads/prestashop-gateway/.git* \
        && sudo rm -Rf ~/Downloads/prestashop-gateway/.idea \
        && sudo rm -Rf ~/Downloads/prestashop-gateway/config* \
        && sudo rm -Rf ~/Downloads/prestashop-gateway/Dockerfile \
        && sudo rm -Rf ~/Downloads/prestashop-gateway/Makefile \
        && sudo rm -Rf ~/Downloads/prestashop-gateway/.env* \
        && sudo rm -Rf ~/Downloads/prestashop-gateway/docker* \
        && sudo rm -Rf ~/Downloads/prestashop-gateway/composer.* \
        && sudo rm -Rf ~/Downloads/prestashop-gateway/*.md \
        && sudo rm -Rf ~/Downloads/prestashop-gateway/LICENSE \
        && cd ~/Downloads \
        && sudo zip -r -q -o prestashop-gateway_$(PLUGIN_VERSION).zip prestashop-gateway \
        && sudo chown freddie:freddie prestashop-gateway_$(PLUGIN_VERSION).zip \
        && sudo chmod 644 prestashop-gateway_$(PLUGIN_VERSION).zip \
        && sudo rm -Rf ~/Downloads/prestashop-gateway
	@echo "Compile file complete"