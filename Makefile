image=yiimp
version=2024.03r01
MAILADDRESS='user@example.com'
DOMAINNAME='yiimp.example.com' 

build:
	git submodule init && git submodule update
	podman build --tag $(image) --target image-prod -f Dockerfile.yiimp 
build-devel:
	git submodule init && git submodule update
	podman build --tag $(image) --target image-devel -f Dockerfile.yiimp 

push:
	podman push $(image) ghcr.io/tpfuemp/$(image):$(version)

run:
	podman rm -i $(image) && podman run -dt --name=$(image) --network=host -v ./config/letsencrypt:/etc/letsencrypt -v ./config:/etc/yiimp -v ./log:/var/log/apache2 -v ./log:/var/log/yiimp -v ./log:/var/www/yaamp/runtime -v ./config/supervisord.conf:/etc/supervisor/conf.d/supervisord.conf $(image)
run-init-letsencrypt:
	podman rm -i $(image) && podman run -dt --name=$(image) --network=host -e MAILADDRESS=$(MAILADDRESS) -e DOMAINNAME=$(DOMAINNAME) -v ./config/letsencrypt:/etc/letsencrypt -v ./config:/etc/yiimp -v ./log:/var/log/apache2 -v ./log:/var/log/yiimp -v ./log:/var/www/yaamp/runtime -v ./config/supervisord.conf:/etc/supervisor/conf.d/supervisord.conf $(image) /usr/local/bin/letsencrypt-yiimp-initial-cert.sh

run-devel:
	podman rm -i $(image) && podman run -dt --name=$(image) --network=host -v ./config/letsencrypt:/etc/letsencrypt -v ./config:/etc/yiimp -v ./yiimp/web:/var/www/ -v ./yiimp/yiimp2:/var/yiimp2/ -v ./log:/var/log/apache2 -v ./log:/var/log/yiimp -v ./log:/var/www/yaamp/runtime -v ./config/supervisord.conf:/etc/supervisor/conf.d/supervisord.conf $(image) /usr/bin/supervisord
