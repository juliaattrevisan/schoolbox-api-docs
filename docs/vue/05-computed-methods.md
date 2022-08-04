# 5. Computed and Methods

Functionally there isn't any _real_ difference to these; they both execute a javascript function. However, their intended usage is contextual. For further info refer to: https://vuejs.org/v2/guide/computed.html#Computed-Caching-vs-Methods

## 5.1 Computed

`computed()` methods are used in the Vue Component's template just like you would a normal property like `fullname`, `firstname`, `lastname` etc in our example payload. However, instead of being concretely defined like those vars, they are instead a _result of a function_.

In `user-list.vue`, find the `computed` node and add this method:

```js
payloadSize () {
    return `${this.payload.length} and, randomly, this text`;
}
```

Note that `payloadSize` wasn't in our original data payload; it only exists as a function that we can access in `user-list.vue` _as if it were a property_, like this:

```html
<ul>
    <li v-for="(user, index) in payload" :key="index">
        {{ payloadSize }} {{ user.fullname }} {{ user.role }}
    </li>
</ul>
```

> `computed()` node is a good place to put macros and shortcuts that tell you things about the `props` you had passed to this component.

## 5.2 Methods

These are basically javascript functions that you can arbitrarily execute. They can pretty much do anything you need.

In this case, we'll add the length of a user's fullname to our `UserList` component:

```js
methods: {
    nameLength (str) {
        return `${str.length} chars long.`;
    }
}
```

```html
<ul>
    <li v-for="(user, index) in payload" :key="index">
        {{ payloadSize }} {{ user.fullname }} {{ user.role }} {{ nameLength(user.fullname) }}
    </li>
</ul>
```

> `methods()` is a good place to put functions that do things which aren't really about the `props` provided, like manipulating ui elements, asynchronous requests, etc.

[Subcomponents](06-subcomponents.md)
