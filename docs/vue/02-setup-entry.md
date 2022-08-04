# 2. Set up a Vue Entry Point

A _Vue Entry Point_ is basically a place where the vue frameworks' javascript loads, then starts up a _Vue Component_. From inside this component you can load other components, process data, and do all the fun front-end stuff.

We'll just build a boring old user list for now, so let's call our Vue Component `UserList`.

## 2.1 Vue Component Setup

Every Vue Component, when launched from a twig environment, must have exactly one parent html element to itself. It can't share this container element with other Vue Components launched in the same twig file.

### 2.1.1 html

In the `container.twig` that we built, add this:

```html
<div id="user-list-container">
    <user-list></user-list>
</div>
```

Note that we decided our component was called `UserList` - this is referenced via the hyphen-separated version of that name, `user-list`.

> This node exists in an arbitrarily-named `#user-list-container` element. You can call it whatever you want but for the sake of simplicity here, just follow along.

The `UserList` component we're about to build requires its own javascript stuff so it can start. For now, we can just pile everything we need into `library/Schoolbox/Greenhouse/templates`.

### 2.1.2 Component Javascript initialiser

Create `library/Schoolbox/Greenhouse/templates/userList.js` and put this inside, which will tell vue how to initialise our `UserList` component into its container element.

```js
import { createApp } from 'vue';
import UserList from './user-list';

createApp({
	components: {
		UserList
    }
}).mount('#user-list-container');
```

-   The `el` arg is the container element's id which we previously named `#user-list-container`.
-   And the `components` is used to list the Vue components that are being referenced within `#user-list-container`

### 2.1.3 Update webpack.config.js

Next, we need to add a path to this javascript file in `webpack.config.js` so the compiler knows where to look for it.

At the top, there's a `module.exports` object literal definition. You'll find other schoobox vue components there. Simply add the path to the `userList.js` file to the `entry` node, which is an index of all the JS entry points that currently exist.

```js
userList: './library/Schoolbox/Greenhouse/templates/userList.js`
```

### 2.1.3 Vue Component basics

The `UserList` component requires a basic Vue component javascript to work. This is where the meat of our component will live; it's where we add other components and process data to render on screen. See https://vuejs.org/v2/guide/single-file-components.html for futher reference.

If you remember before, our initialiser was set to require from `user-list.vue`, so let's do that.

Add `library/Schoolbox/Greenhouse/templates/user-list.vue` and paste this massive slab in:

```js
<script>

/**
 * To display a list of users 
 */

import Toast from '@schoolboxVue/components/toast';

export default {

    /**
     * Components.
     * Any other vue components you also want to use here; they must already be imported
     * at the top of the file.
     */
    components: {
        Toast
    },

    /**
     * Mixins.
     * Basically traits for vue; allows you to share javascript between components in the same
     * manner as a php trait. They must already be imported at the top of the file and will
     * be superpositioned on top of this javascript here, inside export default {}
     * https://vuejs.org/v2/guide/mixins.html
     *
     */
    mixins: [],

    /**
     * Directives
     * Register your own custom directives
     * https://vuejs.org/v2/guide/custom-directive.html
     *
     */
    directives: {},

    /**
     * Props.
     * Immutable props that were passed to this component, usually via twig, in our setup.
     * These are the hyphen-separated vars which are magically computed into camelcase elsewhere.
     * https://vuejs.org/v2/guide/components-props.html
     *
     */
    props: {
        users: {
            type: Array,
            required: true,
            default: () => {
                return [
                    {
                        firstName: 'first name',
                        lastName: 'last name'
                    }
                ]
            }
        },
    },

    /**
     * Data.
     * Mutable properties that can be accessed from within this component via "this" keyword
     * Note that v-if and v-for must never be on the same node together, as this overrides
     * the meaning of "this" in a way you don't expect.
     */
    data () {
        return {
            isLoaded: false
        }
    },

    /**
     * Computed properties.
     * Access the results of a function as if they were a property of an object.
     * https://vuejs.org/v2/guide/computed.html#Computed-Properties
     *
     */
    computed: {

    },

    /**
     * Watchers.
     * Respond directly to changing data
     * https://vuejs.org/v2/guide/computed.html#Watchers
     *
     */
    watch: {

    },

    /**
     * Mounted.
     * Basically, when vue has done all its inits and is ready to go
     * kind of like window.onload
     *
     * Other lifecycle hooks can be added here, ordered relative to their lifecycle order
     * see https://vuejs.org/v2/guide/instance.html#Lifecycle-Diagram for reference
     *
     */
    mounted () {

    },

    /**
     * Methods.
     * Arbitrary javascript functions which operate on the args provided, just like any
     * other ordinary javascript that you might have written before.
     */
    methods: {

    }

}

</script>

<template>
    <div>
        Vue components must have exactly one node directly inside the <em>template</em> node itself.
    </div>
</template>

<style lang="scss" scoped>
    //Styles linked to the component placed here
</style>

```

> Vue components' html template must have exactly one parent node. It'll crash out if you try to do otherwise so don't worry if you forget - npm will remind you.

### 2.1.4 npm run build

Now you can compile and keep it compliing every file save. 

```
npm run watch
```

If you visit the Greenhouse now, it just won't have done anything...but why? It turns out that you need to do one more thing.

### 2.1.5 Include the js npm built for you

If you look in `www/static/js/bundle`, you'll find a `userList.js` which npm built for you in the previous step. It won't have existed earlier.

You need to include this in the `container.twig` file we built earlier.

> You can pre-empt this step by having this script tag in the container.twig when you built it. Note that user-list translated into UserList (you'll encounter this phenomenon throughout vue land)

Add this to the bottom of `container.twig`

```html
<!-- You need this; spawns after you "npm run build" or "npm run watch" for the first time -->
<script src="{{ CDN_PREFIX }}/js/bundle/userList.js"></script>
```

Now you can reload your Greenhouse and somewhere, it should say:

```
Vue components must have exactly one node directly inside the template node itself.
```

You're on the way!

[Payload](03-payload.md)
