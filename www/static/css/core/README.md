## FILES

Here's where you'll find pretty much the entirety of CSS within Schoolbox.

`main.scss` contains all CSS outside of:

-   inline styles
-   styles linked to Vue components
-   styles linked to external JS plugins

`editor.scss` is applied to CKEditor, which allows our CKEditor instances to maintain the same styles as those within main.scss

## SUMMARY

Our CSS currently leans heavily on Foundation 5 (https://get.foundation/sites/docs-v5/)
It provided a very solid framework and contained everything we needed at the time of implementation.
The Foundation documentation linked above is very handy for providing further help when delving further into most styling.
On top of the base Foundation framework plenty of cutomisation/overrides/specific fixes were applied as we saw fit.
As Foundation 5 is now over six years old, it's our intent to gradually pick it apart and replace it with more modern styling.
To allow this, Foundation was pulled into our own repo a little while ago so we can get stuck in and replace it as required.
A `scss` file originally from Foundation will have the comment _Foundation by ZURB_ at the top of the file.
If the file contains custom overrides, these will typically be unerneath the comment _CUSTOM OVERRIDES_
