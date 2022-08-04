# 6. Subcomponents

If you've been following along thus far, your `UserList` component should have this in it:

```html
<template>
    <div>
        Vue components must have exactly one node directly inside the <em>template</em> node itself.

        <ul>
            <li v-for="(user, index) in payload" :key="index">
                {{ payloadSize }} {{ user.fullname }} {{ user.role }} {{ nameLength(user.fullname) }}
            </li>
        </ul>
    </div>
</template>
```

We can use this to explore the concept of subcomponents. Let's say we wanted a universal representation of a user's data; we can define a new component that explicitly renders this.

## 6.1 Setting up a new subcomponent

In `library/Schoolbox/Greenhouse/templates`, create a new file `single-user.vue` and paste the stub code in, along with the usual ritual:

-   provide the `name` of this component (in this case, `single-user`)
-   props that we'll use inside this component

```js
<script>

export default {

    /**
     * Components.
     * Any other vue components you also want to use here; they must already be imported
     * at the top of the file.
     */
    components: {

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
        fullname: {
            type: String,
            required: true
        }
        firstname: {
            type: String,
            required: true
        }
        lastname: {
            type: String,
            required: true
        }
        role: {
            type: String,
            required: true
        }
        route: {
            type: String,
            required: true
        }
        nameLength: {
            type: String,
            required: true
        }
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
        User data is represented using this component
    </div>
</template>

<style lang="scss" scoped>
    //Styles linked to the component placed here
</style>

```

To use this subcomponent, you need to import it into the component which uses it, then specify that it's a component.

In `user-list.vue`, at the top,

```
import SingleUser from './single-user';
```

Then, inside the `components` node,

```
components: {
    'single-user': SingleUser
},
```

This will let you access the new `UserListUser` subcomponent via `<single-user>`, like this, in `user-list.vue`,

```html
<ul>
    <li v-for="user in this.payload">
        <single-user
            :fullname="user.fullname"
            :firstname="user.firstname"
            :lastname="user.lastname"
            :role="user.role"
            :route="user.route"
            :name-length="nameLength(user.fullname)"
        >
        </single-user>
    </li>
</ul>
```

If you compile it and reload the screen, it'll tell you a lot of `User data is represented using this component`. Perfect.

> Note that we didn't have to make a `single-user.js` file or fiddle with webpack. That's because the new `SingleUser` component isn't something that launches from a twig environment; it's only used from within another Vue component.

## 6.2 Defining subcomponent behaviour

Now we can make the subcomponent render out in a nice format. Inside `single-user.vue` you can render the `props` that it was given:

```html
<template>
    <div>
        <div class="card small-12">
            <a :href="route" title="View Profile">
                <img src="/portrait.php?id=230&size=square64" alt="View Profile" />
                <p>{{ firstname }} {{ lastname }}</p>
            </a>
            <p class="meta">{{ nameLength }}</p>
            <p class="meta">{{ role }}</p>
        </div>
    </div>
</template>
```

This renders out a card-style layout with a person's firstname, lastname, role name, and length of name as meta data. Each card links to the user's profile screen.

This `single-user.vue` subcomponent can be imported into any other place and used in a similar manner.

> You can, of course, embellish the output with `computed()` and `methods()` as required.

> There's a special vue syntax for using vars in html attributes, like that anchor tag.

[Async Payload](07-async-payload.md)
