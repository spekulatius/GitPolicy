[GitPolicy](https://github.com/spekulatius/gitpolicy)
===========

[![Build Status](https://api.travis-ci.org/spekulatius/gitpolicy.svg?branch=master)](https://travis-ci.org/spekulatius/gitpolicy)
[![Latest Stable Version](https://poser.pugx.org/spekulatius/gitpolicy/version.svg)](https://github.com/spekulatius/gitpolicy/releases)
[![Latest Unstable Version](https://poser.pugx.org/spekulatius/gitpolicy/v/unstable.svg)](https://packagist.org/packages/spekulatius/gitpolicy)
[![Scrutinizer Code Quality](https://img.shields.io/scrutinizer/g/spekulatius/gitpolicy.svg)](https://scrutinizer-ci.com/g/spekulatius/gitpolicy?branch=master)
[![Total Downloads](https://poser.pugx.org/spekulatius/gitpolicy/downloads.svg)](https://packagist.org/packages/spekulatius/gitpolicy)
[![License](https://poser.pugx.org/spekulatius/gitpolicy/license.svg)](https://github.com/spekulatius/gitpolicy/blob/master/license.md)

GitPolicy helps you and your development team follow guidelines for the usage of git. These guidelines can include the usual git commands, as well as naming conventions for branches and tags.

*Beta state: This package is still in development. Please be careful and patient if you decide to use it.*


Features
--------

This is the set of features to make your life easier:

 * Easy initial set-up combined with installation in one step:

```bash
gitpolicy init
```

 * Definition of forbidden actions (e.g. create new tag, push to master) possible.

 * Expectations for the tag / branch naming (e.g. needs to be semantic, needs to start with the ticket number)

 * Configuration over one file: .gitpolicy.yml


Requirements
------------

 * Should work on all developer Linux machines with projects using git. Windows systems haven't been tested.
 * PHP 5.4.0
 * [Composer](https://getcomposer.org) for the installation


Installation
------------

The installation can either be global or in the project itself. You don't need to have a PHP project to use this!


### Installation for PHP projects

An installation as a development dependency is enough to start using gitpolicy:

```bash
# install the package:
composer install spekulatius/gitpolicy dev-master --dev;
./vendor/spekulatius/gitpolicy/gitpolicy init;

# To ensure this change persists you should commit the composer files to git.
git add composer.json composer.lock .gitpolicy.yml
git commit -nm 'MINOR: adding gitpolicy'
```

### Installation for non-PHP projects

If you are planning to use this tool for non-PHP projects you will need to run the following command to use gitpolicy:

```bash
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer;
composer install -g spekulatius/gitpolicy;
sudo ln -s ~/.composer/vendor/spekulatius/gitpolicy/gitpolicy /usr/local/bin/gitpolicy;
```

Then change into your project folder and run:

```
gitpolicy init
```


Configuration
-------------

All configuration is done in one file: .gitpolicy.yml

With the initialization, an example is copied into your project :) All possible (sensible and non-sense) combinations of options have been listed. Please delete parts which aren't required for your project. By default there shouldn't be a strong policy in place.

Some .gitpolicy.yml examples:

 * [default .gitpolicy.yml](https://github.com/spekulatius/gitpolicy/blob/master/templates/.gitpolicy.yml)
 * [GitPolicy' own .gitpolicy.yml](https://github.com/spekulatius/gitpolicy/blob/master/.gitpolicy.yml)

More to come! If you want to share your .gitpolicy.yml as an example for a specific use case open a pull request ;)


Roadmap and ideas
-----------------

Please see the [issue tracker](https://github.com/spekulatius/GitPolicy/issues) for planned enhancements and the roadmap.

 * 0.1.x:
   * adding tests and bug fixes only
 * 0.2.0:
   * Providing the ability to manage the default commit message by populating sensible default values
 * 0.3.0:
   * MAJOR: verification of commit messages


License
-------

For information regarding the license see [license.md](https://github.com/spekulatius/GitPolicy/blob/master/license.md).