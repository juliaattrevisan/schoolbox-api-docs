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

# Schoolbox RPM Packaging Tools

## Setup

This is still a work in progress.
The spec file has been provided, but the process for downloading and building is still currently manual.

## Build a package
An outline only.
* Download gzipped tarball from github in rpmbuild/SOURCES directory
```
    cd /home/vagrant/rpmbuild
    curl -L --progress-bar --user "githubuser:githubpass" https://github.com/alaress/schoolbox/archive/13.0.0.tar.gz > /home/vagrant/rpmbuild/SOURCES/13.0.0.tar.gz
```
* Run rpm build
```
    rpmbuild -ba --sign SPECS/schoolbox.spec
```
* Copy rpms into repo
```
    cp rpmbuild/RPMS/noarch/schoolbox*13.0.0* /home/vagrant/repo/noarch/ -v
```
* Re-run create repo
```
    createrepo /home/vagrant/repo/
```
* Upload repo to server
