# Making CDN Prefix globally available

* Status: proposed
* Deciders: Amri Sannang
* Date: 2021-03-23

Technical Story: https://schoolbox.atlassian.net/browse/IST-425,
https://schoolbox.atlassian.net/browse/SA-11686

## Context and Problem Statement

The CDN Prefix is a configurable option for setting the base URL via which
static assets (i.e. JavaScript, CSS, images) are included.

This prefix may be configured to either:
* a path relative to the Schoolbox instance (`/static`, as decided in
  [ADR-0001](0001-separateStaticAssetsAndExecutableScriptsWithinWebRoot.md))
* an absolute path to a CDN server, to which the same static assets are deployed

As all requests for static assets must now have a prefix (whether hardcoded to
the `/static` path, or set to the configured value), there must be one or more
methods for making the prefix easily available.

## Decision Drivers

* The configured CDN Prefix must be applied to as many requests for static
  assets as reasonably possible
* The CDN Prefix must be easily accessible to developers, in whichever contexts
  they may need to use it

## Considered Options

1. Make the CDN Prefix available as a global within Twig templates
2. Make the CDN Prefix available as a global within PHPLib templates
3. Hardcode the CDN Prefix to the relative `/static` path for singular requests
4. Inject an object which may determine the CDN Prefix into PHP scripts which
   need to generate a prefixed URL
5. Make `url()` CSS rules always point to relative URLs
6. Within JavaScript files included from the CDN; inject the CDN prefix into its
   configuration
7. Within JavaScript files included from the CDN; hardcode the CDN Prefix to the
   relative `/static` path
8. Within JavaScript files included from the CDN; look up the CDN prefix from a
   value injected into the JavaScript `window` object
9. Within JavaScript files included from the CDN; determine the path via which
   the file was included, so that its own includes may reference the CDN prefix

## Decision Outcome

Chosen options: 1, 2, 3, 4, 5, 6, 7, 8

The developer should consider the context in which they are attempting to
include a link to a static asset:
* if within a Twig/PHPLib template, choose 1 or 2
  ```
  # Option 1: Twig
  <script type="text/javascript" src="{{ CDN_PREFIX }}/javascript/components/schoolbox.min.js?v={{ config.site_version }}"></script>

  # Option 2: PHPLib
  <script type="text/javascript" src="{CDN_PREFIX}/javascript/ckeditor/ckeditor.js?v={SITE_VERSION}"></script>
  ```
* if within a JavaScript plugin, 6 is preferred; then either 7 or 8. 7 gives
  less benefit, but is cleaner code as it doesn't rely upon global variables:
  ```
  # Option 6 (jQuery)
  <script type="text/javascript">
    $('#my-plugin').myPlugin({
        cdnPrefix: "{{ CDN_PREFIX }}"
    })
  </script>

  # Option 6 (Vue)
  <vue-component cdn-prefix="{{ CDN_PREFIX }}"></vue-component>

  # Option 7
  e.append('<img class="dz-pending" width="16" height="16" src="/static/images/foundation/preloader.gif" />'));

  # Option 8
  let config = {
    contentsCss: [
        '/css/core/skin.css.php',
        window.cdnPrefix + '/css/core/editor.css',
    ],
  }
  ```
* if within CSS, use option 5 to include URLs that are relative to the CSS file
* if within SCSS, use option 5, but ensure that the URL is relative to where the
  compiled CSS will be written to
  * each SCSS root file includes a `$cdn-prefix` var: `url()` rules should use
    this
    ```
    background-image: url("#{$cdn-prefix}/images/foundation/preloader.gif");
    ```
* if within PHP: prefer option 1 or 2 if the static asset will be referenced in
  a template. If it won't be (i.e. outputting JSON representation of an object),
  then use option 4:
  ```
  use Schoolbox\Cdn\PrefixGenerator;
  use Schoolbox\IConfigLoader;

  // either get from DI or construct
  /** @var PrefixGenerator $prefixGenerator */
  $prefixGenerator = $this->getDi()->get('cdnPrefixGenerator');

  /** @var IConfigLoader $prefixGenerator */
  $config          = $this->getDi()->get('config');
  $prefixGenerator = new PrefixGenerator($config);

  $thumbnail = $prefixGenerator->generate() . '/images/emails/spacer.gif';
  ```

Option 9 was rejected due to the possible unreliability of determining the
path of the currently executing script, and the unexpected overriding of the
`URL` object by another script. This is how it could work:
```
// Retrieve a script relative to this script's URL
let currentScript = document.currentScript.src;

// this script is somewhere within the CDN root:
// traverse back up to the root, then to the static asset to include.
let relativeUrl = new URL('../../my-script.js', currentScript);
```

### Positive Consequences

The options given for determining the CDN prefix should be comprehensive enough
to cover all cases where a static asset would be included.

### Negative Consequences

The number of places the CDN prefix is available may make it difficult for a
developer to determine how to actually include the prefix.

This document attempts to give examples of where each option is relevant, and
of the reasons for choosing one or the other.

## Pros and Cons of the Options <!-- optional -->

### Option 1



[example | description | pointer to more information | …] <!-- optional -->

* Good, because [argument a]
* Good, because [argument b]
* Bad, because [argument c]
* … <!-- numbers of pros and cons can vary -->

### [option 2]

[example | description | pointer to more information | …] <!-- optional -->

* Good, because [argument a]
* Good, because [argument b]
* Bad, because [argument c]
* … <!-- numbers of pros and cons can vary -->

### [option 3]

[example | description | pointer to more information | …] <!-- optional -->

* Good, because [argument a]
* Good, because [argument b]
* Bad, because [argument c]
* … <!-- numbers of pros and cons can vary -->
