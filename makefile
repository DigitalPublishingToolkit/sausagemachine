.PHONY: checkDependencies checkPHPmodules shoppinglist sausages

DEPENDENCIES = php pandoc python
PHPMODULES = curl

checkDependencies := $(foreach exec,$(DEPENDENCIES), $(if $(shell which $(exec)),,$(error No $(exec) in PATH. Consider apt-get install $(exec))))

checkPHPmodules := $(foreach module,$(PHPMODULES), $(if $(shell php -m | grep -i $(module)),,$(error PHP module $(module) missing.)))

shoppinglist:
	@mkdir -p content
	$(shell chmod a+rwx content)
	$(shell touch user-config.inc.php)
	$(if $(shell cat user-config.inc.php),,$(error Please edit user-config.inc.php.))
	$(info To continue setup visit: http://127.0.0.1:8080/setup.php)
	$(shell php -S 127.0.0.1:8080 -t .)

sausages:
	$(info Sausage Machine ready at: http://127.0.0.1:8080/)
	$(shell php -S 127.0.0.1:8080 -t .)