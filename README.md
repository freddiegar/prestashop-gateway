# Place to Pay Gateway on Prestashop
Plugin Payment for Prestashop 1.5 to 1.7

### Compatibility Version

This library need PHP >= 5.5 with curl, soap and mbstring extensions

| Prestashop | Plugin   |
|------------|----------|
| 1.5.x      | ~2.6.4   |
| 1.6.x      | \>=2.6.4 |
| 1.7.x      | 3.*      |

View releases [here][link-releases]

[link-releases]: https://github.com/freddiegar/prestashop-gateway/releases 

### Install CMS

Create folder placetopaypayment (this is required, with this name)
```bash
mkdir /var/www/html/modules/placetopaypayment
```

Clone Project in modules 
```bash
git clone git@github.com:freddiegar/prestashop-gateway.git /var/www/html/modules/placetopaypayment
```

Install dependencies with composer
```bash
cd /var/www/html/modules/placetopaypayment && composer install
```

### Install with Docker
Install prestashop 1.6 with php 5.5. In folder of project; 
```bash
docker-compose up -d
```