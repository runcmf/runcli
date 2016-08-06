[![Latest Version on Packagist][ico-version]][link-packagist] [![Software License][ico-license]][link-license] [![Total Downloads][ico-downloads]][link-downloads]
#RunCli
Command Line Interface.
    `migrate, seed, generate Eloquent ORM migrations, generate resources`

## Install
Via Composer, command line

``` bash
$ composer require runcmf/runcli
```
Via composer.json
```
    ...
    "require": {
        "runcmf/runcli":  "dev-master"
      },
    ...
```
``` bash
$ composer update
```

## Usage
``` bash
    php cli - for help
    php cli make:seed vendor/runcmf/runbb
    php cli make:migrate vendor/runcmf/runbb
```
![example](ss/ss1.png "DB filled")


### Who do I talk to? ###

* 1f7.wizard@gmail.com
* http://runcmf.ru

## License

Apache License
Version 2.0. Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/runcmf/runcli.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-Apache%202-green.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/runcmf/runcli.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/runcmf/runcli
[link-license]: http://www.apache.org/licenses/LICENSE-2.0
[link-downloads]: https://bitbucket.org/1f7/runcli
