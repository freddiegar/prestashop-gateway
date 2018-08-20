# Prestashop Gateway to PlacetoPay

[PlacetoPay][link-placetopay] Plugin Payment for [Prestashop][link-prestashop] 1.5 to 1.7 Stable

## Prerequisites

- `php` >= 5.6.0
- `ext-curl`
- `ext-soap`
- `ext-json`
- `prestashop` >= 1.5

## Compatibility Version

| Prestashop | Plugin   |
|------------|----------|
| 1.5.x      | ~2.6.4   |
| 1.6.x      | >=2.6.4 |
| 1.7.x      | 3.*      |

View releases [here][link-releases]

## Manual Installation

Create `placetopaypayment` folder (this is required, with this name)

```bash
mkdir /var/www/html/modules/placetopaypayment
```

Clone Project in modules
 
```bash
git clone https://github.com/freddiegar/prestashop-gateway.git /var/www/html/modules/placetopaypayment
```

Set permissions and install dependencies with composer

```bash
cd /var/www/html/modules/placetopaypayment \ 
    && sudo setfacl -dR -m u:www-data:rwX -m u:`whoami`:rwX `pwd` \ 
    && sudo setfacl -R -m u:www-data:rwX -m u:`whoami`:rwX `pwd` \
    && composer install
```

## Docker Installation

Install PrestaShop 1.6 (latest in 1.6 branch) with PHP 5.6 (and MySQL 5.7). In folder of project;
 
```bash
cd /var/www/html/modules/placetopaypayment
make install
```

Then... (Please wait few minutes, while install ALL and load Apache :D to continue), you can go to
 
- [store](http://localhost:8787)
- [admin](http://localhost:8787/adminstore)

> If server return error code (400, 404, 500) you can status in docker logs until that installation process end, use:

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

See details in `docker-compose.yml` file or run `make config` command

### Customize docker installation

Default versions

- PrestaShop: 1.6
- PHP: 5.6
- MySQL: 5.7

Others installation options are [here][link-docker-prestashop], You can change versions in `.env` file

```bash
# PrestaShop 1.7 with PHP 7.0
PS_VERSION=1.7-7.0

# PrestaShop 1.6.1.1 with PHP 5.6
PS_VERSION=1.6.1.1

# PrestaShop latest with PHP 5.6 and MySQL 5.5
PS_VERSION=latest
MYSQL_VERSION=5.5
```

### Binding ports

Ports by default in this installation are

- Web Server (`WEB_PORT`): 8787 => 80
- Database (`MYSQL_PORT`): 33060 => 3306

> You can change versions in `.env` file

### Used another database in docker

You can override setup in docker, rename `docker-compose.override.example.yml` to `docker-compose.override.yml` and [customize](https://store.docker.com/community/images/prestashop/prestashop) your installation, by example

```yaml
version: "3.2"

services:
  # This service is shutdown
  database:
    entrypoint: "echo true"

  prestashop:
    environment:
      # IP Address or name from database to use
      DB_SERVER: my_db
```


## Setup Module

Install and setup you `login` and `trankey` in your [store](http://localhost:8787/adminstore)!

Maybe you need to setup on shipping carriers.

Enjoy development and testing!

### SMTP Email

Change email configuration to use [mailtrap.io][link-mailtrap] in development

```mysql
USE prestashop;

UPDATE ps_configuration SET value='2' where name = 'PS_MAIL_METHOD';
UPDATE ps_configuration SET value='smtp.mailtrap.io' where name = 'PS_MAIL_SERVER';
UPDATE ps_configuration SET value='user' where name = 'PS_MAIL_USER';
UPDATE ps_configuration SET value='password' where name = 'PS_MAIL_PASSWD';
UPDATE ps_configuration SET value='off' where name = 'PS_MAIL_SMTP_ENCRYPTION';
UPDATE ps_configuration SET value='2525' where name = 'PS_MAIL_SMTP_PORT';
```

## Error Codes

| Code | Description                                    |
|------|------------------------------------------------|
| 1    | Create payments table is failed                |
| 2    | Add email column is failed                     |
| 3    | Add id_request column is failed                |
| 4    | Add reference column is failed                 |
| 5    | Update ipaddres column is failed               |
| 6    | Login and TranKey is not set                   |
| 7    | Payment is not allowed by pending transactions |
| 8    | Payment process is failed                      |
| 9    | Reference (encrypt) is not found               |
| 10   | Reference (decrypt) is not found               |
| 11   | Id Request (decrypt) is not found              |
| 12   | Try to change payment without status PENDING   |
| 13   | PlacetoPay connection is failed                |
| 14   | Order related with payment not found           |
| 15   | Get payment in payment table is failed         |
| 16   | Command not available in this context          |
| 99   | Un-known error, module not installed?          |
| 100  | Install process is failed                      |
| 201  | Order id is not found                          |
| 202  | Order id is not loaded                         |
| 301  | Customer is not loaded                         |
| 302  | Address is not loaded                          |
| 303  | Currency is not loaded                         |
| 304  | Currency is not supported by PlacetoPay        |
| 401  | Create payment PlacetoPay is failed            |
| 501  | Payload notification PlacetoPay not is valid   |
| 601  | Update status payment PlacetoPay fail          |
| 801  | Get order by id is failed                      |
| 901  | Get last pending transaction is failed         |
| 999  | Un-know error                                  |

## Troubleshooting

If shop is not auto-installed, then rename folder `xinstall` in container and installed from [wizard](http://localhost:8787/install)

```bash
make bash-prestashop
mv xinstall install
```

> This apply to last versions from PrestaShop (>= 1.7)

## Compile Module

In terminal run

```bash
make compile
```

Or adding version number in filename use

```bash
make compile PLUGIN_VERSION=_X.Y.Z
```

## Quality

During package development I try as best as possible to embrace good design and development practices, to help ensure that this package is as good as it can
be. My checklist for package development includes:

- Be fully [PSR1][link-psr-1], [PSR2][link-psr-2], and [PSR4][link-psr-1] compliant.
- Include comprehensive documentation in README.md.
- Provide an up-to-date CHANGELOG.md which adheres to the format outlined
    at [keepachangelog][link-keepachangelog].
- Have no [phpcs][link-phpcs] warnings throughout all code, use `composer test` command.

[link-placetopay]: https://www.placetopay.com
[link-prestashop]: https://www.prestashop.com
[link-releases]: https://github.com/freddiegar/prestashop-gateway/releases
[link-docker-prestashop]: https://store.docker.com/community/images/prestashop/prestashop/tags
[link-mailtrap]: https://mailtrap.io/
[link-psr-1]: https://www.php-fig.org/psr/psr-1/
[link-psr-2]: https://www.php-fig.org/psr/psr-2/
[link-psr-4]: https://www.php-fig.org/psr/psr-4/
[link-keepachangelog]: https://keepachangelog.com
[link-phpcs]: http://pear.php.net/package/PHP_CodeSniffer
