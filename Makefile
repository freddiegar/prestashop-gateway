CONTAINER_PS=plugin_ps_prestashop
CONTAINER_DB=plugin_ps_database
CURRENT_FOLDER=$(shell pwd)
UID=$(shell id -u)

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
	docker exec -u root -it $(CONTAINER_PS) bash

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
        && sudo zip -r -q -o placetopaypayment_$(PLUGIN_VERSION).zip placetopaypayment \
        && sudo chown $(UID):$(UID) placetopaypayment_$(PLUGIN_VERSION).zip \
        && sudo chmod 644 placetopaypayment_$(PLUGIN_VERSION).zip \
        && sudo rm -Rf ~/Downloads/placetopaypayment
	@echo "Compile file complete: ~/Downloads/placetopaypayment_$(PLUGIN_VERSION).zip"