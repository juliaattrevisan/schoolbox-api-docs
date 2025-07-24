#!/usr/bin/env sh

# ensure that the storage folder exists
mkdir -p ./data/docker/storage

# currently, the `php82-bookworm` image must be built locally before everything
# else
docker compose --progress plain build php82-bookworm

# start default environment:
# this has Schoolbox's code folder mounted as a local mount, thus changes made
# locally are available immediately within the container.
docker compose --progress plain up -d
