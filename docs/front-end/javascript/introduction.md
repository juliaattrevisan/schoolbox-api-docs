# JavaScript'ing in Schoolbox

Most of Schoolbox's JS is written in jQuery. This is now considered legacy code, [Vue.js](https://vuejs.org/) is the framework we are using for all future JS.

Currently we can only write in ES5, but we will be implementing Babel soon into our build process so that we can write in ES2015+.

## Vue.js
Documentation: https://vuejs.org/v2/guide/

### Tips
Download the Vue.js devtools browser extension for [Chrome](https://chrome.google.com/webstore/detail/vuejs-devtools/nhdogjmejiglipccpnnnanhbledajbpd?hl=en) or [Firefox](https://addons.mozilla.org/en-US/firefox/addon/vue-js-devtools/).

### Usage
Current usage is to just create a new Vue instance at the bottom of your twig file. See `Comms/News/create.twig` for an example. This will be changing to the [Single File Component](https://vuejs.org/v2/guide/single-file-components.html) structure once we adjust our build process to cater for it.

### Common Gotchas
* Using moustache syntax `{{ var }}` instead of the adjusted syntax to avoid twig conflict: `${ var }`.
    * This often happens when copy pasting example Vue code, or converting Twig markup to Vue.
    * This problem will go away with Single File Components.
* Sometimes the UI will not react to a property change. See https://vuejs.org/v2/guide/reactivity.html
* When using a Vue variable in an html attribute, you don't use any template syntax, you use `v-bind` (or the `:` shorthand) and put your variable in directly.
    * Eg. `<a :href="vueJsProperty"></a>`
    * If you need to do a mix of strings and a vue.js property, you can concat like so: `<a :href="'something-' + vueJsProperty"></a>`
    
## Axios
Axios will be replacing jQuery's ajax functionality to make http requests.

Documentation: https://github.com/axios/axios