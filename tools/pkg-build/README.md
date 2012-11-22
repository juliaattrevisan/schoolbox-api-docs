# Schoolbox APT Packaging Tools

## Setup

* Ensure vagrant and virtualbox are installed
* Run vagrant to bring up a build server
```
    vagrant up
```
* Login to the fresh build server
```
    vagrant ssh
```
* Create the .gnupg directory
```
    mkdir -vp ~/.gnupg
```
* Copy the keyrings to the .gnupg directory
```
    cp -v /vagrant/*.gpg ~/.gnupg/
```
* All ready to go

## Build a package

* Ensure build server is running
```
    vagrant up
```
* Login to build server
```
    vagrant ssh
```
* Build a package
```
    /vagrant/pkg-build.sh [commithash/committag] [main/dev] [release/snapshot]
```
* Built packages will be located in
```
    ~/build-area/
```
