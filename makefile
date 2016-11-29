.PHONY: checkDependencies checkPHPmodules install run

DEPENDENCIES = php pandoc
PHPMODULES = curl

checkDependencies := $(foreach exec,$(DEPENDENCIES), $(if $(shell which $(exec)),is some string,$(error "No $(exec) in PATH. Consider apt-get install $(exec)")))

checkPHPmodules := $(foreach module,$(PHPMODULES), $(if $(shell php -m | grep -i $(module)),is some string,$(error "PHP module $(module) missing.")))

install:
	@mkdir -p content
	$(shell chmod a+rwx content)
	php -S 127.0.0.1:8080 -t .

run:
	php -S 127.0.0.1:8080 -t .
