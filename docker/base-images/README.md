# Schoolbox Base Images

These images are not intended to be run directly; they are intended as:
- base images upon which other images may be built
- images whose purpose is to generate artifacts for other images

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
