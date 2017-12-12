# Place to Pay Gateway on Prestashop
Plugin Payment for Prestashop 1.5 to 1.7

### Compatibility Version

This library need PHP >= 5.5 with curl, soap extensions

| Prestashop | Plugin                 |
|------------|------------------------|
| 1.5.x      | [v2.6.4] [link-v2.6.4] |
| 1.6.x      | [v3.0.0] [link-v3.0.0] |
| 1.7.x      | [v3.0.0] [link-v3.0.0] |

[link-v2.6.4]: https://github.com/freddiegar/prestashop-gateway/releases/tag/v3.0.0
[link-v3.0.0]: https://github.com/freddiegar/prestashop-gateway/releases/tag/v2.6.4

### Install CMS

Create folder placetopaypayment (this is required, with this name)
```
mkdir /var/www/prestashop/modules/placetopaypayment
```

Clone Project in modules 
```
git clone git@github.com:freddiegar/prestashop-gateway.git /var/www/html/modules/placetopaypayment
```

Install dependencies with composer
```
cd /var/www/html/modules/placetopaypayment && composer install
```

### Install with Docker
This use prestashop 1.6 with php 5.5. In folder of project; 
```
docker-compose up -d
```