# Sausage Machine


### Features

* RESTful [API](doc/api.md) for manipulating Git repositories and executing Makefiles
* Bootstrap publishing projects on GitHub
* Automatic generation of output files whenever the user pushes to GitHub
* Custom Makefiles, CSS styles and scripts on a per-project basis
* Various templates to choose from, as well as the possibility to continuously incorporate updates from them (using *git rebase*)
* Lightweight frontend
* Caching and automatic cleanup of repositories in the backend


### Requirements

* PHP with cURL module
* [Pandoc](http://pandoc.org/)
* Make
* (Many templates will also require Python to be installed)


### Installation on server

* Create a `content` directory and make it writable to the webserver process

  e.g. `mkdir content && chmod a+rwx content` in the current directory

* Make sure you have the latest version of [Pandoc](http://pandoc.org/installing.html) installed

* Create a `user-config.inc.php` file to hold your custom configuration.

  e.g. `touch user-config.inc.php` in the current directory

  Use this file to modify (overwrite) the values of any configuration settings from `config.inc.php`. Using a separate
  file prevents your changes to be undone by future updates to this file.

  Setting configuration variables in `user-config.inc.php` works like this:

      <?php
      $config['key1'] = 'value1';
      $config['key2'] = 'value2';

* For integration with GitHub, register a [new application](https://github.com/settings/applications/new) with them:

  point the *Authorization callback URL* to `github.php?push` in the current directory (e.g.
  `https://hpt.publishinglab.nl/github.php?push`, except on using own domain)

  copy the *Client ID* that is displayed after creating the application and set it as the configuration variable `github_client_id`

  copy the *Client Secret* and set is as the configuration variable `github_client_secret`

  set the configuration variables `github_push_as` and `github_useragent` to your GitHub username

* Using a web browser, navigate to `setup.php`

  Make sure all checks pass.

* Copy the displayed SSH public key associated with your server, and [add it](https://github.com/settings/ssh) to
your GitHub user

  This does not necessarily need to be the user the the application is registered to, but the software needs to
be able to push to GitHub as the user defined by the configuration variable `github_push_as`.


### Adding custom templates

* Create a publicly accessible Git repository to hold your template

* Make sure your template contains a Makefile, at least with a target "all"

  Certain aspects of the Sausage Machine operation, e.g. where uploaded files end up relative to the template root,
  are governed by heuristics. Those will be described in detail in the future. In the mean time, please consult
  [hybrid.inc.php](hybrid.inc.php).

* Add the the repository's clone URL and description to the `repos` configuration variable.


### Limitations / Future work

* see [TODO](doc/todo.md)


### Credits

Based on the [Hybrid Publishing Toolkit](http://networkcultures.org/blog/publication/from-print-to-ebooks-a-hybrid-publishing-toolkit-for-the-arts/) and the ongoing practice and experimentation at the PublishingLab by a number
of people. Sausage Machine by [Gottfried Haider](http://gottfriedhaider.com/).
