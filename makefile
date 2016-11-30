.PHONY: checkDependencies checkPHPmodules shoppinglist sausage

DEPENDENCIES = php pandoc python
PHPMODULES = curl

checkDependencies := $(foreach exec,$(DEPENDENCIES), $(if $(shell which $(exec)),,$(error No $(exec) in PATH. Consider apt-get install $(exec))))

checkPHPmodules := $(foreach module,$(PHPMODULES), $(if $(shell php -m | grep -i $(module)),,$(error PHP module $(module) missing.)))

shoppinglist:
	@mkdir -p content
	$(shell chmod a+rwx content)
	$(shell touch user-config.inc.php)
	$(if $(shell cat user-config.inc.php),,$(error Please edit user-config.inc.php.))
	$(shell open local_setup.html)
	$(info To continue setup visit: http://127.0.0.1:8080/setup.php)
	$(shell php -S 127.0.0.1:8080 -t .)

sausage:
	$(info Sausage Machine ready at: http://127.0.0.1:8080/)
	$(shell open local_index.html)
	$(shell php -S 127.0.0.1:8080 -t .)