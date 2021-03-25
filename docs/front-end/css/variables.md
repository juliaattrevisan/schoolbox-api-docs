# CSS Variables in Schoolbox

Schoolbox makes use of [CSS variables](https://developer.mozilla.org/en-US/docs/Web/CSS/Using_CSS_custom_properties)
to set the color of elements based on the school's configured skin builder
configuration.

Schoolbox uses CSS variables instead of SASS variables for instance-specific
settings so that:
* instances don't need to compile SASS based on their settings
* CSS may be served from a CDN, instead of from the instance itself

Developers who intend to update SASS stylesheets may continue to use the
pre-existing SASS color variable names. However, they will need to be aware of
the limitations of CSS variables, and the workarounds for those limitations.


## Skin configuration options

| Config Label | Config Key | SASS Variable | CSS Variable |
|---|---|---|---|
| Top Menu Icon Colour                               | `skin_colour_navigation_foreground`        | `$navigation-foreground`        | `--navigation-foreground`        |
| Top Menu Background Colour                         | `skin_colour_navigation_background`        | `$navigation-background`        | `--navigation-background`        |
| Top Menu Hover Colour                              | `skin_colour_navigation_hover`             | `$navigation-hover`             | `--navigation-hover`             |
| Side Menu and Notification Panel Text Colour       | `skin_colour_offcanvas_foreground`         | `$off-canvas-foreground`        | `--off-canvas-foreground`        |
| Side Menu and Notification Panel Background Colour | `skin_colour_offcanvas_background`         | `$off-canvas-background`        | `--off-canvas-background`        |
| Side Menu Hover Colour                             | `skin_colour_offcanvas_hover`              | `$off-canvas-hover`             | `--off-canvas-hover`             |
| User Account Menu Text Colour                      | `accounts_menu_text_colour`                | `$accounts-menu-foreground`     | `--accounts-menu-foreground`     |
| User Account Menu Background Colour                | `accounts_menu_bg_colour`                  | `$accounts-menu-background`     | `--accounts-menu-background`     |
| User Account Menu Hover Colour                     | `accounts_menu_hover_colour`               | `$accounts-menu-hover`          | `--accounts-menu-hover`          |
| Primary Interactive Content Foreground Colour      | `skin_colour_content_ui_foreground`        | `$content-ui-foreground`        | `--content-ui-foreground`        |
| Primary Interactive Content Background Colour      | `skin_colour_content_ui_background`        | `$content-ui-background`        | `--content-ui-background`        |
| Primary Interactive Content Hover Colour           | `skin_colour_content_ui_hover`             | `$content-ui-hover`             | `--content-ui-hover`             |
| Primary Interactive Content Selected-State Colour  | `skin_colour_content_ui_selected`          | `$content-ui-selected`          | `--content-ui-selected`          |
| Form Submit Button Text Colour                     | `skin_colour_content_ui_submit_foreground` | `$content-ui-submit-foreground` | `--content-ui-submit-foreground` |
| Form Submit Button Background Colour               | `skin_colour_content_ui_submit_background` | `$content-ui-submit-background` | `--content-ui-submit-background` |
| Secondary Interactive Content Foreground Colour    | `skin_colour_accent_foreground`            | `$accent-foreground`            | `--accent-foreground`            |
| Secondary Interactive Content Background Colour    | `skin_colour_accent_background`            | `$accent-background`            | `--accent-background`            |
| Secondary Interactive Content Hover Colour         | `skin_colour_accent_hover`                 | `$accent-hover`                 | `--accent-hover`                 |
| Side Menu Logo Background Colour                   | `skin_colour_logo_background`              | `$logo-background`              | `--logo-background`              |
| Body Background Colour                             | `skin_colour_body_bg`                      | `$body-bg`                      | `--body-background`              |
| Login Background Colour                            | `skin_colour_login_background`             | `$login-form-background`        | `--login-form-background`        |
| Form Input Border Colour                           | `skin_colour_form_input_border_color`      | `$form-border-color`            | `--form-border-color`            |
| Form Input Hover Colour                            | `skin_colour_form_background_hover_color`  | `$form-bg-hover-color`          | `--form-bg-hover-color`          |

The SASS variables are defined in `www/static/css/foundation/utils/_variables.scss`, as
they were before CSS variables: however, their value is now set to the CSS
variable instead of to an overridable color value.

The CSS variables are created by `www/css/core/skin.css.php`, as are the
contrasting colors and complex derived colors.


## Contrasting colors

Each config option also has CSS and SASS variables for a contrasting color (used
as a legible text color for backgrounds with this color value): these have the
same name as the base CSS/SASS variable, suffixed by `-contrast`.

> Contrasting colors are separate from the base variables because there is no
> way to calculate whether a color is "light" or "dark" (and then choose a color
> based on that calculation) using CSS alone.
>
> Instead, they are calculated using `\Alaress\Color\Transformer::calculateTextColor`:
> this method calculates the color's [relative luminance](https://en.wikipedia.org/wiki/Relative_luminance),
> and then returns a contrasting light or dark color based on that.


## Complex derived colors

The following colors are derived from skin configuration options, but their
calculations do not map easily to calculations that CSS supports:

| Purpose | SASS Variable | CSS Variable | Derivation
|---|---|---|---
| Color for text not within islands     | `$body-foreground`                 | `--body-foreground`                 | Body Background Color, passed through `\Alaress\Color\Transformer::calculateForegroundColor`
| Color for H1 heading not with islands | `$body-foreground-h1`              | `--body-foreground-h1`              | As above, but contrasts more heavily
| Color for top menu icon when selected | `$navigation-foreground-highlight` | `--navigation-foreground-highlight` | Top Menu Background Color, passed through `\Alaress\Color\Transformer::calculateForegroundColor` and then `\Alaress\Color\Transformer::calculateTextColor`

> `\Alaress\Color\Transformer::calculateForegroundColor` performs several steps
> for calculating a color:
> * determines a base foreground from a background, using `calculateTextColor`
> * layers that color onto the background color at a given opacity
> * boosts saturation of that color by a given amount


## Manipulating colors based on CSS variables

Each CSS variable defined above also has variables defined for each of its
channel values:
* RGB channels: CSS variable name, suffixed by `-r`, `-g`, `-b`
* HSL channels: CSS variable name, suffixed by `-h`, `-s`, `-l`
* Alpha channel: CSS variable name, suffixed by `-a`

e.g. CSS var `--body-foreground` will have corresponding vars:
* `--body-foreground-r`
* `--body-foreground-g`
* `--body-foreground-b`
* `--body-foreground-h`
* `--body-foreground-s`
* `--body-foreground-l`
* `--body-foreground-a`

### CSS color manipulation functions

The new functions in `www/static/css/foundation/utils/_functions.scss` rely upon the
existence of these variables to be able to create new color definitions based on
CSS variables.

Given a CSS variable definition (e.g. `var(--body-background)`), they will
create a new color definition using the `rgba()` or `hsla()` CSS color formats,
performing adjustments on individual channels using `calc()`.

e.g. `css-tint(var(--body-foreground), 10%)` will create:
```
rgba(
    calc(var(--body-foreground-r) + (255 - var(--body-foreground-r)) * 0.1),
    calc(var(--body-foreground-g) + (255 - var(--body-foreground-g)) * 0.1),
    calc(var(--body-foreground-b) + (255 - var(--body-foreground-b)) * 0.1),
    var(--body-foreground-a)
)
```

SASS variables may be used as the color var parameters, but these variables must
resolve to a CSS variable at compile time; otherwise, a compilation error will
be raised.

> Note that SASS does not and cannot know whether the CSS vars these functions
> use are defined. For this reason, it's recommended to use SASS variables from
> the skin configuration options above as color parameters, e.g.
> `css-tint($body-bg, 10%)`

#### `css-tint($colorVar, $percent)`

Creates a color that is `$colorVar`, `$percent` closer toward white.

#### `css-shade($colorVar, $percent)`

Creates a color that is `$colorVar`, `$percent` closer toward black.

#### `css-lighten($colorVar, $percent)`

Creates a color that is `$colorVar`, with its lightness channel increased `$percent`.

#### `css-darken($colorVar, $percent)`

Creates a color that is `$colorVar`, with its lightness channel reduced `$percent`.

#### `css-adjust-color($colorVar, $hue: null, $saturation: null, $lightness: null, $alpha: null)`

Creates a color that is `$colorVar`, with its HSLA channels adjusted by the given amounts.

#### `css-scale-color($colorVar, $hue: null, $saturation: null, $lightness: null, $alpha: null)`

Creates a color that is `$colorVar`, with its HSLA channels scaled by the given percentages.

Percentages greater than 0% scale the value toward the highest possible value;
less than 0% toward the lowest possible value.
