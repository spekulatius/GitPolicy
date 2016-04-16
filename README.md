GitPolicy
=========

[![Build Status](https://api.travis-ci.org/spekulatius/GitPolicy.svg?branch=master)](https://travis-ci.org/spekulatius/GitPolicy)
[![Latest Stable Version](https://poser.pugx.org/spekulatius/gitpolicy/version.svg)](https://github.com/spekulatius/gitpolicy/releases)
[![Latest Unstable Version](https://poser.pugx.org/spekulatius/gitpolicy/v/unstable.svg)](https://packagist.org/packages/spekulatius/gitpolicy)
[![Scrutinizer Code Quality](https://img.shields.io/scrutinizer/g/spekulatius/gitpolicy.svg)](https://scrutinizer-ci.com/g/spekulatius/gitpolicy?branch=master)
[![Total Downloads](https://poser.pugx.org/spekulatius/gitpolicy/downloads.svg)](https://packagist.org/packages/spekulatius/gitpolicy)
[![License](https://poser.pugx.org/spekulatius/gitpolicy/license.svg)](https://github.com/spekulatius/gitpolicy/blob/master/license.md)

GitPolicy helps you and your development team follow [guidelines for the usage of git](https://github.com/spekulatius/gitpolicy). These guidelines can include the usual git commands, as well as naming conventions for branches and tags.

*Beta state: This package is still in development. Please be careful and patient if you decide to use it.*


Features
--------

This is the set of features to make your life easier:

 * One command to do everything. See installation.

 * Definition of forbidden actions (e.g. create new tag, push to master) possible.

 * Expectations for the tag / branch naming (e.g. needs to be semantic, needs to start with the ticket number)

 * Configuration over one file: .gitpolicy.yml


Requirements
------------

This has been developed on a Debian destribution with Linux in mind. It should work on similar platforms. Mac OS: Maybe. Windows? No idea.

The only direct requirement is PHP 5.4.37.

Note: During the installation [Composer](https://getcomposer.org) will be installed and used to manage the dependencies of GitPolicy.


Installation
------------

The installation and set up are combined into one single command here for you to run. It will take all needed steps to install, configurature, re-initalize or update to your needs. This is how it works:

1. Change into your project directory.

2. check and run the following command in your project folder:

    ```bash
    # install or update composer - we need this to manage the dependencies
    curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer;

    # install the package as a global dependency and symlink it.
    composer install -g spekulatius/gitpolicy;
    sudo ln -s ~/.composer/vendor/spekulatius/gitpolicy/gitpolicy /usr/local/bin/gitpolicy;

    # run the initial steps, this configures the githook as well as the inital config file.
    gitpolicy init;

    # commit the change
    git add composer.json composer.lock .gitpolicy.yml;
    git commit -m 'CHORE: Adding gitpolicy :sunny:'
    ```

Done :sunglasses:

Configuration
-------------

All configuration is done in one file: .gitpolicy.yml

With the initialization, an example is copied into your project :) All possible (sensible and non-sense) combinations of options have been listed. Please delete parts which aren't required for your project. By default there shouldn't be a strong policy in place.

Some .gitpolicy.yml examples:

 * [default .gitpolicy.yml](https://github.com/spekulatius/gitpolicy/blob/master/templates/.gitpolicy.yml)
 * [GitPolicy' own .gitpolicy.yml](https://github.com/spekulatius/gitpolicy/blob/master/.gitpolicy.yml)

More to come! If you want to share your .gitpolicy.yml as an example for a specific use case open a pull request ;)


[Roadmap and ideas](https://github.com/spekulatius/GitPolicy/issues)
-----------------

Please see the issue tracker for planned enhancements and the roadmap.

 * 0.1.x:
   * adding tests and bug fixes only
 * 0.2.0:
   * [Providing the ability to manage the default commit message by populating sensible default values](https://github.com/spekulatius/GitPolicy/issues/5)
 * 0.3.0:
   * [MAJOR: verification of commit messages](https://github.com/spekulatius/GitPolicy/issues/6)


License
-------

For information regarding the license see [license.md](https://github.com/spekulatius/GitPolicy/blob/master/license.md).
