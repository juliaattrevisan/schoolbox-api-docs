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

### Gotchas
* Unlike jQuery's ajax library, axios does not set the XHR header - you need to do this manually.
* By default Axios sends JSON payloads in POST requests. This is not compatible with our backend architecture, which expects `application/x-www-form-urlencoded`, so you need to manually set the `content-type` header.

In the future, we will likely have a single place in which global axios defaults can be set (see [axios documentation on defaults](https://github.com/axios/axios#global-axios-defaults)). We will need to at least set the following:

    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
    axios.defaults.headers.post['Content-Type'] = 'application/x-www-form-urlencoded';

For now, you can supply custom headers per request:

    // Options
    var getHeaders = {
        'X-Requested-With' : 'XMLHttpRequest'
    };

    var postHeaders = {
        'X-Requested-With' : 'XMLHttpRequest',
        'content-type': 'application/x-www-form-urlencoded'
    }
    
    axios.get(yourUrl, {headers: getHeaders}).then(function(response) {
        ...
    });
    
    // yourData is an object containing the post params to send; if there are none, provide an empty array [] instead
    axios.post(yourUrl, yourData, {headers: postHeaders}).then(function(response) {
        ...
    });

The complete set of options accepted by axios can be found [here](https://github.com/axios/axios#request-config).