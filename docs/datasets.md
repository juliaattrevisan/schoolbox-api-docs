# Schoolbox Data

### Data options available
Each of the below options can be provisioned in vagrant.

#### Core Data Sets
Please do not update the core data sets without checking with the relevant teams (PST, Support, Dev)

* `legacy.tar.gz` - Old internal only data with lots of weird edge cases. Ask Support before changing.
* `sales.tar.gz` - Older demo data used in public Sandboxes for prospects and on Schoolbox Demo. Ask PST before changing.
* `demo.tar.gz` - Newer demo data used in public Sandboxes for prospects and on Schoolbox Demo. Ask PST before changing.
* `initial.tar.gz` - The initial data set deployed to new schools. Very very clean. Ask Support, PST AND James before changing.
* `large.tar.gz` - Huge data set based on Schoolbox Help - provision to break things

#### Custom Data Sets
Please feel free to create new data sets and share them as required.

E.g. `sam.tar.gz`


### Updating & Saving Data
***WARNING! Ensure your post-provision.sh script is empty, or you will commit those changes too.***

#### 1. Checkout a specific version of Schoolbox.

This is required if you are updating any of the core data sets, but somewhat optional if just creating things for yourself.
You must checkout a stable release git tag, not an unstable branch like `master` or `develop`, as capturing a dataset from an unstable branch will cause any update scripts that are subsequently merged to not be applied when the dataset is provisioned/deployed.

Check https://github.com/alaress/schoolbox/releases for the latest versions.
```
git checkout 16.5.19-1;
```

#### 2. Login to the vagrant VM
```
vagrant ssh;
sudo su;
```

#### 3. Execute the `save-data.sh` script
Execute the following for usage assistance:
```
/usr/share/schoolbox/tools/save-data.sh --help
```

#### 4. Provision your saved data
1. Edit `vagrant.yaml` with a text editor
2. Change the `data_filename` value to match the saved data you wish, eg
```
data_filename: "myname.tar.gz"
```
then:
```
vagrant provision
```

