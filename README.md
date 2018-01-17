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

Install dependencies with composer
```bash
cd /var/www/html/modules/placetopaypayment && composer install
```

## Docker Installation

Install Prestashop 1.6 with PHP 5.5 (and MySQL 5.7). In folder of project;
 
```bash
docker-compose up -d
```

Then... (Please wait 5 min, while install all and load Apache :D to continue), you can go to
 
- [store](http://localhost:8787)
- [admin](http://localhost:8787/adminstore) 

Admin data access
 
- email: demo@prestashop.com
- password :prestashop_demo

### Another docker in prestashop

Others installation options are [here](https://store.docker.com/community/images/prestashop/prestashop), yo can see changing `FROM` in Dockerfile, for instance

```yaml
# Install Prestashop 1.6 with PHP 5.5 (Default option)
FROM prestashop/prestashop:1.6-5.5
...
# change to Install Prestashop 1.7 with PHP 7.0
FROM prestashop/prestashop:1.7-7.0
``` 

### Port bind

Ports by default in this installation are

- Web Server: 8787 => 80 [localhost](http://localhost:8787) - [in docker](http://ip_address)
- MySQL: 33060 => 3306

## Setup module

Install and setup you login and trankey in [store](http://localhost:8787/adminstore)!

Enjoy development and test!