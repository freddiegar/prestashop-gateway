FROM prestashop/prestashop:1.6-5.5

COPY . /var/www/html/modules/placetopaypayment

RUN php -r "readfile('http://getcomposer.org/installer');" | php -- --install-dir=/usr/bin/ --filename=composer \
    && composer install --quiet -d /var/www/html/modules/placetopaypayment
