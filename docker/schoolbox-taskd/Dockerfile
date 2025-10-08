# Currently this always requires taskd from Schoolbox's Dockerhub:
# this requires `docker login`.
# Alternatives:
# * build from local
# * pull from ECR (requires `docker login` to ECR, but can manage using AWS credentials?)
# * continue to pull from Dockerhub (reqs login still, login is shared)
ARG PRIVATE_CONTAINER_REGISTRY=docker.io/alaress
FROM ${PRIVATE_CONTAINER_REGISTRY}/taskd:latest

# Change configuration depending on whether using taskd from local source?
