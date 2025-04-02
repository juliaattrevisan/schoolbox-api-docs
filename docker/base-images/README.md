# Schoolbox Base Images

These images are not intended to be run directly; they are intended as base
images upon which other images may be built.

It is not expected that the base images will need to be rebuilt upon every merge
or pull request: they should only need to be rebuilt periodically.

To build the images:
```shell
# Run from the base Schoolbox folder, with the base image folder's build context
sudo docker build -t alaress/$imageName docker/base-images/$imageName/

# Test that the image is at least built
sudo docker run -a stdin -a stdout -i -t alaress/$imageName sh

# Push to Dockerhub (and maybe ECR?)
sudo docker login -u alaress -p
sudo docker push alaress/$imageName
```
