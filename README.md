# Place to Pay Gateway on Prestashop

Plugin Payment for Prestashop 1.5 to 1.7

## Compatibility Version

This library need PHP >= 5.5 with curl, soap and mbstring extensions

| Prestashop | Plugin   |
|------------|----------|
| 1.5.x      | ~2.6.4   |
| 1.6.x      | \>=2.6.4 |
| 1.7.x      | 3.*      |

View releases [here][link-releases]

[link-releases]: https://github.com/freddiegar/prestashop-gateway/releases 

## Manual Installation

Create folder placetopaypayment (this is required, with this name)

```bash
mkdir /var/www/html/modules/placetopaypayment
```

Clone Project in modules
 
```bash
git clone git@github.com:freddiegar/prestashop-gateway.git /var/www/html/modules/placetopaypayment
```

Set permissions and install dependencies with composer

```bash
cd /var/www/html/modules/placetopaypayment \ 
    && sudo setfacl -dR -m u:www-data:rwX -m u:`whoami`:rwX `pwd` \ 
    && sudo setfacl -R -m u:www-data:rwX -m u:`whoami`:rwX `pwd` \
    && composer install
```

## Docker Installation

Install Prestashop 1.6 with PHP 5.6 (and MySQL 5.7). In folder of project;
 
```bash
make install
```

Then... (Please wait ~8 min, while install ALL and load Apache :D to continue), you can go to
 
- [store](http://localhost:8787)
- [admin](http://localhost:8787/adminstore)

***ATTENTION:*** If server return error code (400, 404, 500) you can status in docker logs until that installation process end, use:

```bash
make logs-prestashop
```

__Preshtashop Admin Access__
 
- email: demo@prestashop.com
- password: prestashop_demo

__MySQL Access__

- user: root
- password: admin
- database: prestashop

See details in `docker-compose.yml` 

### Customize docker installation

Default versions

- PrestaShop: 1.6
- PHP: 5.6
- MySQL: 5.7

Others installation options are [here](https://store.docker.com/community/images/prestashop/prestashop/tags), You can change versions in `.env` file

```bash
# Prestashop 1.7 with PHP 7.0
PS_VERSION=1.7-7.0

# Prestashop 1.6.1.1 with PHP 5.6
PS_VERSION=1.6.1.1

# Prestashop latest with PHP 5.6 and MySQL 5.5 
PS_VERSION=latest
MYSQL_VERSION=5.5
```

### Binding Ports

Ports by default in this installation are

- Web Server (`WEB_PORT`): 8787 => 80
- Database (`MYSQL_PORT`): 33060 => 3306

## Setup module

Install and setup you `login` and `trankey` in your [store](http://localhost:8787/adminstore)!

Enjoy development and test!
