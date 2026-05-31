.PHONY: consume docker-down docker-up install serve test

install:
	composer install
	composer install-git-hooks

serve:
	php -S 0.0.0.0:8082 -t public public/index.php

consume:
	php bin/consume-requests.php

test:
	./vendor/bin/pest

docker-up:
	docker compose up --build -d

docker-down:
	docker compose down
