#!/bin/bash

readonly INITIAL_DIR=$(pwd)
readonly FILE_PATH=$(readlink -f "$0")
readonly FULL_PATH=${FILE_PATH%/*}
readonly INST_DIR=$(readlink -f "$FULL_PATH"/../../)
readonly ARG_BRANCH="--branch"
readonly ARG_GITHUB_TOKEN="--github-token"
readonly APTLY_SCRIPT="$INITIAL_DIR/aptly.sh" # We'll download the aptly helper script from GitHub to this file
readonly ARTIFACT_DIR="$INITIAL_DIR/package-artifacts"

#global variables
declare branch='master'
declare github_token
declare version
declare majversion
declare shortversion
declare buildcommit

function parse_args() {
  local errors=0
  while [[ $# -gt 0 ]]; do
    case $1 in
    "$ARG_BRANCH")
      branch=$2
      shift 2
      ;;
    "$ARG_GITHUB_TOKEN")
      github_token=$2
      shift 2
      ;;
    *)
      echo "Unrecognised option: '$1'" >&2
      errors='1'
      shift
      ;;
    esac
  done
  if [[ "$errors" == '1' ]]; then
    echo "Errors detected, exiting" >&2
    exit 1
  fi
}

# Validate CLI args
function validate_args() {
  local errors='0'
  if [[ -z $github_token ]]; then
    echo "If you are creating and uploading DEB packages you must provide a value for $ARG_GITHUB_TOKEN" >&2
    errors=1
  fi
  if [[ "$errors" == '1' ]]; then
    echo "Errors detected, exiting" >&2
    exit 1
  fi
}

function determine_version() {
  echo "Checking version to build"
  if [[ -f ../schoolbox-release-"$branch"/version ]]; then
    version=$(cat ../schoolbox-release-"$branch"/version)
    echo "Building version: $version"
  else
    echo "Unable to determine version" >&2
    exit 1
  fi

  shortversion=$(echo "$version" | cut -d'-' -f1)
  majversion=$(echo "$version" | cut -d'.' -f1-2)

  if [[ -f .git/ref ]]; then
    buildcommit=$(cat .git/ref)
  else
    echo "Unable to determine build commit"
    ls -1 .git/
  fi
}

# Download aptly helper script
function download_aptly_publish_script() {
  echo -e "\n\n**** Downloading aptly helper script from GitHub..." >&2

  local owner="alaress"
  local repo="puppet-alaress"
  local path="environments/production/modules/schoolbox/files/repo/aptly.sh"
  local branch="stable"
  local github_endpoint="https://api.github.com/repos/${owner}/${repo}/contents/${path}?ref=${branch}"

  if ! curl --silent --show-error -H "Authorization: token ${github_token}" -H 'Accept: application/vnd.github.v3.raw' -L "${github_endpoint}" --output "$APTLY_SCRIPT"; then
    echo "Unable to download aptly helper script. Exiting" >&2
    exit 1
  fi

  if ! head -n1 "$APTLY_SCRIPT" | grep -q "/bin/bash"; then
    cat "$APTLY_SCRIPT"
    echo "Unable to download aptly helper script. Exiting." >&2
  fi

  echo "aptly helper script downloaded to $APTLY_SCRIPT" >&2
  chmod +x "$APTLY_SCRIPT"
}

function setup_directories() {
  echo "Creating artifact directory"
  mkdir -vp "$ARTIFACT_DIR"
  echo "Creating package directory"
  mkdir -vp "$INITIAL_DIR"/"$shortversion"
}

function unpack_build_artifact() {
  if [[ ! -f ../build-artifact/"$branch"-build.tar.gz ]]; then
    echo "Unable to locate build artifact: ../build-artifact/$branch-build.tar.gz"
    exit 1
  fi
  echo "Unpacking build artifact"
  cd "$INITIAL_DIR"/"$shortversion"

  if ! tar xzf ../build-artifact/"$branch"-build.tar.gz; then
    echo "Error unpacking build artifact"
    exit 1
  fi
}

function verify_build_artifact() {
  echo "Check we have the right commit in the artifacts for the package"
  echo "Build commit: $buildcommit"
  if [[ -f version.json ]]; then
    buildcommit_in_artifacts=$(grep -oP '(?<="build_commit":")[^"]*' <version.json)
    echo "Artifacts commit: $buildcommit_in_artifacts"
  else
    echo "Unable to check commit in artifacts is valid, don't package"
    exit 1
  fi

  if [ ! "$buildcommit_in_artifacts" == "$buildcommit" ]; then
    echo "Build commit in artifacts is not the same as commit in current build. Do not package."
    exit 1
  fi
}

function build_changelog() {
  curdate=$(date -R)
  echo "Building changelog/version file for package"
  echo "schoolbox ($version) stable; urgency=low

  * Release of the $majversion series

 -- Schoolbox (Schoolbox Signing Key) <support@schoolbox.com.au>  $curdate" >debian/changelog
}

function package() {
  echo "Creating source tarballs for packages"
  cd "$INITIAL_DIR"
  if ! tar czf schoolbox_"$shortversion".orig.tar.gz "$shortversion"; then
    echo "Unable to build source tarball"
    exit 1
  fi
  cd "$shortversion"

  echo "Building Package"
  if ! debuild -i -I -us -uc; then
    echo "Build failed for schoolbox"
    exit 1
  fi

  echo "[] Copy deb files to package-artifacts"
  cp -vf ../*.deb "$ARTIFACT_DIR"

  cd "$INST_DIR"

  echo "Creating package tarball"
  cd "$INITIAL_DIR"
  if ! tar czf package.tar.gz "$ARTIFACT_DIR"; then
    echo "Unable to tarball packages"
    exit 1
  fi
  mv -v package.tar.gz "$ARTIFACT_DIR"/
  mv -v schoolbox_*.orig.tar.gz "$ARTIFACT_DIR"/schoolbox_"$version".orig.tar.gz
}

# Upload DEB package to aptly
function upload_to_aptly() {
  distros="bionic jammy"
  if [[ $branch == "master" ]]; then
    repo="schoolbox-stable"
  else
    repo="schoolbox-unstable"
  fi
  for distro in $distros; do
    echo "Uploading DEB to aptly for $distro..." >&2
    $APTLY_SCRIPT --package "$(realpath "$ARTIFACT_DIR"/*deb)" --local-repo "$repo-$distro"

    echo "Upload complete to $distro. Package: schoolbox=$version" >&2
  done
}

function main() {
  set -e
  parse_args "$@"
  validate_args
  cd "$INST_DIR" || exit 1
  echo "operating in dir; $INST_DIR"
  determine_version
  download_aptly_publish_script
  setup_directories
  unpack_build_artifact
  verify_build_artifact
  build_changelog
  package
  upload_to_aptly
}

main "$@"
