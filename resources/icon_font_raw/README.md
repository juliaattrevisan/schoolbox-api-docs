# icon_font_raw

This README covers the purpose of this directory and a brief overview of the files

# Why?

This directory is created so we have version control over files used to generate the
icon font used within Schoolbox and so that we can confirm the raw font files have
been updated as part of the PR process.

The previous method was to upload files to separate Google Drive locations. In some
cases, certain files that manage glyph codes for the font weren't updated after a new
icon was added, so a developer would come along months later, add a new icon, and find
that IcoMoon (our font generator of choice) had reused a code already used by an icon
previously added. Later on that dev would update the generated font in their build and
find that some icons that had been previously working fine were now displaying entirely
wrong, meaning the dev would then have to manually adjust the _icons.scss file class
definitions to make sure all of the codes lined up as expected whilst also having to
readd the glyph that had been 'overwritten'.

# Files

* schoolbox_icons_concepts.ai - The Adobe Illustrator file that contains raw versions
  of each icon. This is where the icons are 'designed'.
* schoolbox_icons_final.ai - The final Illustrator file, containing individual,
  completed icons organised on named layers sorted alphabetically on a 512px grid.
* selection.json - The file used by IcoMoon to generate the Schoolbox icon set.
  Effectively the current 'state' of the icon font.
* style.css - Holds a record of all the codes used as part of the icon font and
  the classes that will apply the icon style in CSS.
* schoolbox-v1.0.zip - The compiled, zipped version of the font and supporting files,
  such as selection.json and style.css. Kept mainly as a reference of the most recent,
  entire package generated from IcoMoon.

# How do I use these files / How do I update the icon font?

The process is covered in Confluence, on the following page
https://schoolbox.atlassian.net/wiki/spaces/DEV/pages/2068414627/Icons
