# Schoolbox Base Images

These images are not intended to be run directly; they are intended as base
images upon which other images may be built.

It is not expected that the base images will need to be rebuilt upon every merge
or pull request: they should only need to be rebuilt periodically.

It's also not expected that they will ever need to be built or pushed manually:
the Github actions for building and pushing base images
[build-and-push-*.yaml](../../.github/workflows/)
ought to be able to build and push automatically.

To build the images:
```shell
# Run from the base Schoolbox folder, with the base image folder's build context
sudo docker build -t alaress/$imageName:latest docker/base-images/$imageName/

# Test that the image is at least built
sudo docker run -it alaress/$imageName:latest sh

# Push to Dockerhub (and later ECR):
# on prompt for password, paste from Bitwarden either:
# * `dockerhub - Schoolbox` password
# * `Concourse CI - credentials.yml` note key `docker-password`
sudo docker login -u alaress
sudo docker push alaress/$imageName:latest
```
