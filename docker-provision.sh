#!/usr/bin/env sh

# Run the provision script in the schoolbox-cli container
docker exec -it schoolbox-cli tools/docker-provision.sh
