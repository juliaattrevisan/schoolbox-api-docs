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

## Restore Schoolbox's CKEditor Configuration

Schoolbox has customised configuration for CKEditor in `config.js`: the previous
step overwrote this custom configuration.

Restore Schoolbox's CKEditor configuration by reverting the changes made to it:

```
git restore www/javascript/ckeditor/config.js
```

## Commit CKEditor build

As with any other code change, the new CKEditor build needs to be committed:

```
git add www/javascript/ckeditor/
git commit
```
