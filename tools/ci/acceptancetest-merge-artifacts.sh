#!/bin/bash

# When concourse runs the acceptance tests, each shard will produce its own set of artifacts.
# However, we only want a single artifact to upload to S3 at the end of the job.
# This script merges all those artifacts together and creates a single tar file at the end.

initialdir=`pwd`;

shardFolderPrefix="built-artifacts-shard-"

branch='master'
if [ "${1:-}" != "" ]; then
    branch="$1"
    shift
fi

echo "Branch: $branch"

echo "Preparing output directory"
mkdir -vp "$initialdir/built-artifacts"

mergedArtifactsDir="$initialdir/built-artifacts/artifacts"
rm -rf "$mergedArtifactsDir"
mkdir -vp "$mergedArtifactsDir"


mergedAny=0
for shardDir in "$initialdir"/$shardFolderPrefix*; do
    if [ ! -d "$shardDir" ]; then
        continue
    fi

    shardDirName="$(basename "$shardDir")"
    shardNumber="${shardDirName#$shardFolderPrefix}"

    if [ ! -d "$shardDir/artifacts" ]; then
        echo "No artifacts directory found from shard $shardNumber"
        continue
    fi

    echo "Merging shard artifacts from shard $shardNumber:"

    for artifact in "$shardDir/artifacts/"*; do
        if [ ! -e "$artifact" ]; then
            continue
        fi

        artifactName="$(basename "$artifact")"
        newArtifactName="shard-$shardNumber-$artifactName"
        cp -a "$artifact" "$mergedArtifactsDir/$newArtifactName"
        echo "  - $artifactName"

        mergedAny=1
    done
done

if [ "$mergedAny" -eq 0 ]; then
    echo "No shard artifacts were found"
    exit 1
fi

echo "Creating merged artifact"
tar -czf "$initialdir/built-artifacts/$branch-acceptancetest.tar.gz" -C "$initialdir/built-artifacts" artifacts

echo "Artifact List: `readlink -f $initialdir/built-artifacts/`";
ls -lhR "$initialdir/built-artifacts/"

exit 0
