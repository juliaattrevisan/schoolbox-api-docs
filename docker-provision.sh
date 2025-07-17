#!/usr/bin/env sh

# Add tests that containers are actually up, or just run docker-up

# Run the provision script in the schoolbox-cli container
docker exec -it schoolbox-cli tools/docker-provision.sh
