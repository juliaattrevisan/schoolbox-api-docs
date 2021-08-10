CKEditor Upgrade Process
==========

## Download a CKEditor build

Visit the CKEditor online builder: https://ckeditor.com/cke4/builder

* Choose the Standard Preset
* Click the "Upload build-config.js" button
* Upload the `build.config.js` file in this folder
* Verify that the "Moono-Lisa" skin is selected
* Verify that the languages "English" and "English (Australian)" are selected
* Verify that you've selected the "Optimized" build
* Agree with the Terms of use, and Download

## Install the CKEditor build into Schoolbox

With the file downloaded by the previous step:
* extract the contents of the `ckeditor` folder into this folder, overwriting
  files that already exist

> Schoolbox has created custom plugins for CKEditor: these are not part of the
> downloaded build, thus they won't be overwritten by the new build install.
>
> Schoolbox has also customised the backend of the Nanospell spell check: this
> customisation is also separate from the downloaded build.
> (The custom backend exists in `www/ajax/nanospell/server/ajax/php/tinyspell.php`)

## Restore Schoolbox's CKEditor Configuration and Custom Styles

Schoolbox has customised configuration for CKEditor in `config.js`: the previous
step overwrote this custom configuration.

Schoolbox also has a custom set of styles available in its CKEditor instances in
`styles.js`: this custom set removes a host of unused styles, and adds a
highlighted note style.

Restore Schoolbox's CKEditor configuration and styles by reverting the changes
made to them:

```
git restore www/static/js/ckeditor/config.js www/static/js/ckeditor/styles.js
```

## Update customised plugins (optional)

Schoolbox may contain customised versions of plugins: you may wish to keep those
customised plugins up-to-date with changes in the core plugins.

The core plugin is probably part of the build, so you may have to visit that
plugin's official page and download its source code in order to compare.

Schoolbox contains the following customised plugins:
* `schoolboxuploadimage` (customised `uploadimage`)

## Commit CKEditor build

First, remove the examples and vendor code which the downloaded CKEditor build
contains:

```
rm -rf www/static/js/ckeditor/samples www/static/js/ckeditor/vendor
```

Then, as with any other code change, the new CKEditor build needs to be
committed:

```
git add www/static/js/ckeditor/
git commit
```
