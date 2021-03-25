# Separate static assets and executable scripts within web root

* Status: Accepted
* Deciders: James Leckie, Andy Ryan, Amri Sannang
* Date: 2021-03-01

Technical Story: https://schoolbox.atlassian.net/browse/IST-425,
https://schoolbox.atlassian.net/browse/SA-11621

## Context and Problem Statement

Schoolbox's web root folder `/www/` contains both static assets (i.e. images,
JavaScript, styles) and executable PHP scripts. This is a holdover from the days
when the entire Schoolbox application was served from this folder.

In order to maximize the benefit of deploying Schoolbox to cloud infrastructure,
static assets need to be servable from a content delivery network (CDN).

The following considerations need to be taken into account:
* How will static assets be deployed to the CDN?
* Not all instances will use the CDN: how will static assets be served for these
  instances?
  * Example 1: staging instances cannot use the CDN, as developers may wish to
    test changes to static assets before deploying to production
  * Example 2: some instances may be accessible on networks with policies which
    prohibit network access to the CDN
* How will the distinction between static assets and scripts be made apparent to
  developers working on the Schoolbox product?

## Decision Drivers

* Cloud deployment: dedicated CDNs can serve static content more efficiently
  than a web server which must also serve scripts
* Versioning: static assets can be sharded by Schoolbox version, thus solving
  problems where a new version is running CSS or JavaScript that is cached from
  a previous version

## Considered Options

1. move all static assets to a subfolder of the web root, otherwise preserving
   their folder structure
   * i.e. `/www/javascript/script.js` is moved to
     `/www/subfolder/javascript/script.js`
2. move all static assets to a folder alongside the web root, preserving
   structure
   * i.e. `/www/javascript/script.js` is moved to
     `/new-folder/javascript/script.js`
3. move all static assets to a subfolder of the web root, not necessarily
   preserving their folder structure
   * i.e. `/www/javascript/script.js` could be moved to
     `/www/subfolder/js/script.js`
4. move all static assets to a folder alongside the web root, not necessarily
   preserving their folder structure
   * i.e. `/www/javascript/script.js` could be moved to
     `/new-folder/js/script.js`

## Decision Outcome

Chose option 1 (move all static assets to a subfolder of the web root, otherwise
preserving their folder structure), because:
* instances which do not use the CDN will still need to serve static assets
  from the same web server virtual host which serves Schoolbox
  * creating a new web server virtual host is not a trivial process, because
    allowances need to be made within the school's network for each virtual host
  * it should still be possible to enforce the static vs. script separation on
    the web server configuration level (i.e. scripts will never be run in the
    static asset folder, static assets will never be served from outside of it)
* preserving the folder structure minimises the changes required to templates
  * significant changes to templates will already be required so that all links
    to static assets reference the CDN, if it is configured
* serving all static assets from a subfolder of the web root allows the CDN to
  be disabled by simply setting the CDN URL to a relative URL stub

The subfolder `/www/static/` was chosen as the destination folder for all static
assets: these assets will now be accessible via `$schoolboxHost/static/`.
