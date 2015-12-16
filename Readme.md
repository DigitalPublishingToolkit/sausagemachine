# Sausage Machine

## Features

* RESTful [API](doc/api.md) for manipulating Git repositories
* Render books using Makefiles, which are executed on the server
* Multitude of templates to start from
* Foster experimentation in Makefiles, scripts
* Bootstrap book projects
* Integration with GitHub
* Automatic generation of output files whenever the user pushes to repository


## Installation

* Create a `content` directory and make it writable to the webserver process

  e.g. by executing `mkdir content && chmod a+rwx content` in the current directory
  
* Install the latest version of [Pandoc](http://pandoc.org/installing.html) on the server

* Create a create `user-config.inc.php` in the root folder of your installation.

  e.g. by executing `touch user-config.inc.php` in the current directory

  Use this file to modify (overwrite) any configuration settings from `config.inc.php`. This prevents your changes from being overwritten by future updates to `config.inc.php` by updates.

  Setting configuration values in `user-config.inc.php` works like this:

      <?php
      $config['key1'] = 'value1';
      $config['key2'] = 'value2';

* For GitHub integration, register a [new application](https://github.com/settings/applications/new) with GitHub:

  point the *Authorization callback URL* to `github.php` in the current directory (e.g. `https://hpt.publishinglab.nl/github.php`, except on your own server)

  copy the *Client ID* that is displayed after creating the application and set it as the configuration value `github_client_id`

  copy the *Client Secret*, below, and set is as the configuration value `github_client_secret`
  
  set the configuration value `github_push_as` and `github_useragent` to your GitHub username

* XXX: SSH


### Adding a template repository

* XXX


## Limitations / Next steps

* The historically grown [base template](https://github.com/DigitalPublishingToolkit/template-test) of the INC needs some improving.

* Currently the Makefiles are being executed in the regular context of the webserver process, which might be a security concern on shared hosts. It should be pretty straightforward to sandbox this, using chroot or containers.

* The Sausage Machine can currently only setup repositories on the user's behalf on GitHub. It'd be nice to have a built-in Git hosting for standalone application (e.g. in an *Bibliotheca*-type setup).

* Some git operations might need additional locking to handle multiple, concurrent users?

* Support for bundling a static version of Pandoc?

* The Sausage Machine needs a logo.
