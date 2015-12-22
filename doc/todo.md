### Limitations / Future work

* The historically grown [base template](https://github.com/DigitalPublishingToolkit/template-test) used by the INC should be revisited.

  e.g. a common base path for (relatively specified) image files, across output and editing formats

* The Makefiles are currently being executed in the regular context of the webserver process, which might be a security concern on shared hosts. It should be pretty straightforward to sandbox this, using chroot or containers.

* The fronend was put together in a very rushed manner, and should probably be re-implemented by someone who knows what she's doing, using backbone.js or similar :)

* After creating a repository on GitHub, the user should be (optionally) asked for his email address, whether they want to receive news, and whether the system should automatically attempt to rebase their work when the template gets updated (and/or when Pandoc does).

* The Sausage Machine heuristics and Makefile guidelines need to be properly documented.

* The "edit" tab should perhaps be made a bit more general: the heuristic which files on the left to show could be rethought, executing the Makefile doesn't update the list, only a single HTML file is assumed to be generated.

* API: Fold repos and temps together?

* It be nice to have an alternative to GitHub, e.g. a built-in Git hosting, à la Bibliotheca

* For GitHub integration, the system currently makes use of the SSH keypair associated with the server's webserver user. Since a keypair can only be associated to a single user on GitHub, this might be an issue. Perhaps there are ways to make Git use a custom keypair file for the SSH push?

* The "projects" tab should directly link to the various output files in each repository, and perhaps show the book's cover in a grid view.

* Some file operations in [git.inc.php](../git.inc.php) should probably also take the git lock to prevent concurrency issues.

* Git clone and fetch should also include subprojects.

* Split "make clean" in "make clean" (remove temporary files) and "make mrproper" (remove temporary files and output files)?

* CI using multiple branches needs testing.

* Support for bundling a static version of Pandoc? Automatic updates of Pandoc?

* The "import" tab also wants a clickable link as an alternative to drag & drop.

* Server side logging might be useful.

* The [api.md](api.md) is sightly outdated.

* If a Makefile has no targets, ignore_targets should be relaxed.
