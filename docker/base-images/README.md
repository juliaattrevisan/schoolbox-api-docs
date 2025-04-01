# Schoolbox Base Images

These images are intended to be the base images upon which Schoolbox's images
are built.

Currently, they are the base for Schoolbox's PHP-FPM image

To build the images:
```shell
cd $imageDir

# Build image
sudo docker build -t schoolbox/$imageName .

# Test that the image is at least built
sudo docker run -a stdin -a stdout -i -t schoolbox/$imageName sh

# Push to Dockerhub (and maybe ECR?)
sudo docker login -u alaress -p
sudo docker push schoolbox/$imageName
```
