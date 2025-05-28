# change image name
image_name=fairbrix
# version tag
version=V2.0.2
# local storage for container dumps
container_savepath=${CONTAINER_SAVEPATH}

# change STRATUM_UPDATEPASS to your own password
STRATUM_UPDATEPASS=tu8tu5

# coin paths
walletuserid=2000
walletgroupid=2000
user_path=/home/walletuser
wallet_directory=.fairbrix
wallet_daemon=fairbrixd

#change container registry path and credentials
registry=ghcr.io/<username>
registry_username=xxx
registry_password=xxx

gitclone:
	git clone https://github.com/Fairbrix/fairbrix

build:
	podman build --tag ${image_name} --build-arg STRATUM_UPDATEPASS=${STRATUM_UPDATEPASS} --build-arg walletuserid=${walletuserid} --build-arg walletgroupid=${walletgroupid} -f Dockerfile
push:
	podman push ${image_name} ${registry}/${image_name}:${version}
save:
	podman save -o ${container_savepath}container-${image_name}-${version}.tar ${image_name}
load:
	podman load -i ${container_savepath}container-${image_name}-${version}.tar
load-gz:
	podman load -i ${container_savepath}container-${image_name}-${version}.tar.gz
run:
	podman rm -i ${image_name} && podman run --name=${image_name} -dt --network=host --log-driver=journald -v /dev/log:/dev/log -v ${user_path}/${wallet_directory}:${user_path}/${wallet_directory} ${image_name} ${wallet_daemon} # -maxconnections=8
run-reindex:
	podman rm -i ${image_name} && podman run --name=${image_name} -dt --network=host --log-driver=journald -v /dev/log:/dev/log -v ${user_path}/${wallet_directory}:${user_path}/${wallet_directory} ${image_name} ${wallet_daemon} -maxconnections=8 -reindex
