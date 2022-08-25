# Webpack in Schoolbox

[Webpack](https://webpack.js.org/) is the tool we use to bundle our Vue, Javascript, and Sass files. It's packed full of options/features but for the most part there's only a few points to consider on a regular basis.

## Add new entry point

A new entry point needs to be added whenever you add a new Vue setup or create a new JS bundle. Within `webpack.config.js` (found in the root directory), in `entry` add your new bundle:

```js
assessmentCalendar: './library/Schoolbox/Learning/Assessment/Calendar/vue/assessmentCalendar.js',
```

```html
<script src="{{ CDN_PREFIX }}/js/bundle/assessmentCalendar.js?v={{ config.site_version }}"></script>
```

As seen here with `assessmentCalendar`, the same name should be used for the bundle name and linked .js file. This way it's easy to identify the corresponding .js file when referencing the `<script>` tag.

## Compiling the new entry point

To create the new bundle, run `npm run build`. After this, the new bundle will appear within `www/static/js/bundle` optimised for development. To continually compile saved changes while working, use `npm run watch`.