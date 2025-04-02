# Schoolbox Artifact Images

These images are not intended to be run directly; their purpose is to build
artifacts which other images rely upon.

To build the images:
```shell
# Run from the base Schoolbox folder:
# artifact images generally require access to Schoolbox's entire code base in
# order to build their artifacts
cd ../../
sudo docker build -t alaress/$imageName -f docker/artifact-images/$imageName/Dockerfile .

# Test that the image is at least built
sudo docker run -a stdin -a stdout -i -t alaress/$imageName sh

# Push to Dockerhub (and maybe ECR?)
sudo docker login -u alaress -p
sudo docker push alaress/$imageName
```
