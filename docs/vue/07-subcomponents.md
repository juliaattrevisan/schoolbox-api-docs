# 6. Subcomponents

If you've been following along thus far, your `UserList` component should have this in it:

```html
<template>
    <div>
        Vue components must have exactly one node directly inside the <em>template</em> node itself.

        <ul>
            <li v-for="user in this.payload">
                {{ payloadSize }} {{ user.fullname }} {{ user.role }} {{ nameLength(user.fullname) }}
            </li>
        </ul>
    </div>
</template>
```

We can use this to explore the concept of subcomponents. Let's say we wanted a universal representation of a user's data; we can define a new component that explicitly renders this.

## 6.1 Setting up a new subcomponent

In `library/Schoolbox/Greenhouse/templates`, create a new file `user.vue` and paste the stub code in, along with the usual ritual:

* provide the `name` of this component (in this case, `user`)
* props that we'll use inside this component

```js
<script>
    export default {
        /**
         * Name.
         * The name of the component that we have here. This name will be used in the container
         * to spawn this component.
         */
        name: "user",

        /**
         * Mixins.
         * Basically traits for vue; allows you to share javascript between components in the same
         * manner as a php trait. They must already be imported at the top of the file and will
         * be superpositioned on top of this javascript here, inside export default {}
         */
        mixins: [],

        /**
         * Props.
         * Immutable props that were passed to this component, usually via twig, in our setup.
         * These are the hypen-separated vars which are magically computed into camelcase elsewhere.
         */
        props: [
            'fullname',
            'firstname',
            'lastname',
            'role',
            'route',
            'name-length'
        ],

        /**
         * Components.
         * Any other vue components you also want to use here; they must already be imported
         * at the top of the file.
         */
        components: {
        },

        /**
         * Data.
         * Mutable properties that can be accessed from within this component via "this" keyword
         * Note that v-if and v-for must never be on the same node together, as this overrides
         * the meaning of "this" in a way you don't expect.
         */
        data() {
            return {
            }
        },

        /**
         * Mounted.
         * Basically, when vue has done all its inits and is ready to go
         * kind of like window.onload
         */
        mounted: function () {
        },

        /**
         * Computed properties.
         * Access the results of a function as if they were a property of an object.
         */
        computed: {
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
```

To use this subcomponent, you need to import it into the component which uses it, then specify that it's a component.

In `user-list.vue`, at the top,

```
import User from './user.vue';
```

Then, inside the `components` node,

```
components: {
    'user': User
},
```

This will let you access the new `User` subcomponent via `<user>`, like this, in `user-list.vue`,

```html
<ul>
    <li v-for="user in this.payload">
        <user :fullname="user.fullname"
              :firstname="user.firstname"
              :lastname="user.lastname"
              :role="user.role"
              :route="user.route"
              :name-length="nameLength(user.fullname)">
        </user>
    </li>
</ul>
```

If you compile it and reload the screen, it'll tell you a lot of `User data is represented using this component`. Perfect.

> Note that we didn't have to make a `user.js` file or fiddle with webpack. That's because the new `User` component isn't something that launches from a twig environment; it's only used from within another Vue component.

## 6.2 Defining subcomponent behaviour

Now we can make the subcomponent render out in a nice format. Inside `user.vue` you can render the `props` that it was given:

```html
<template>
    <div>
        <div class="card small-12">
            <a :href="this.route" title="View Profile">
                <img src="/portrait.php?id=230&size=square64" alt="View Profile" />
                <p>{{ this.firstname }} {{ this.lastname }}</p>
            </a>
            <p class="meta">{{ this.nameLength }}</p>
            <p class="meta">{{ this.role }}</p>
        </div>
    </div>
</template>
```

This renders out a card-style layout with a person's firstname, lastname, role name, and length of name as meta data. Each card links to the user's profile screen.

This `user.vue` subcomponent can be imported into any other place and used in a similar manner.

> You can, of course, embellish the output with `computed()` and `methods()` as required.

> There's a special vue syntax for using vars in html attributes, like that anchor tag.

