# 8 Pagination

Sometimes you're working with a large data se and it's not nice to show it all at the same time. We can paginate it using a third party component `VuejsPaginate`.

This is already included in a special minified file `schoolbox-vue.min.js` which is secretly compiled elsewhere. You can just use it, for now, like this:
(if you can't find the file, try "import VuejsPaginate from 'vuejs-paginate-next';")

```html
<!-- You need this; spawns after you "npm run build" for the first time -->
<script src="{{ CDN_PREFIX }}/js/bundle/userList.js"></script>
```

## 8.1 Register this component

Add a `Paginate` component to the corresponding node in `user-list.vue`.

```js
components: {
   SingleUser,
   VuejsPaginate
},
```

## 8.2 Set up Pagination vars and methods

Set up a local var to remember which page we're currently showing. Remember that vue is stateful in this way.

```js
data () {
    return {
        loading: true,
        allUsers: [],
        numPerPage: 5, //Number of items to show, per page
        page: 1 //The current page number
    }
},
```

Underneath your `axios` request to obtain a payload, we can add a little thing which manages the browser's history to simulate Back button behaviour while paginating. You don't need to think too much about this; it should mostly _just work_.

```js
/* Set up listener for window history state, for pagination refresh
 * Don't pushState when window.onpopstate executes otherwise the Back
 * button will only work once.
 *
 * https://stackoverflow.com/questions/50647136/forward-button-not-working-after-history-pushstate
 */
window.onpopstate = (event) => {
    if (!event.state) {
        // Return to defaults
        this.page = 1;
    } else if (event.state.page) {
        this.page = event.state.page;
    }
};
```

Further down, in `methods()` we need to add this:

```js
/**
 * Update the pushState history in the browser to simulate navigation
 * with async requests
 *
 * @param { Number } - The page to change to
 */
onPaginate (page) {
    const params = `?p=${page}`;
    window.history.pushState({ page: page }, '', params);
},
```

This adds querystring parameters to indicate which page we're on as we cycle through the `Paginate` component's navigation.

## 8.3 Set up computed methods

The `Paginate` component requires a couple more computed methods. Add these to the `computed()` node:

```js

/**
 * The total number of pages available for the data set obtained
 * 
 * @returns { Number }
 */
pageCount () {
    return Math.ceil(this.allUsers.length / this.numPerPage);
},


/**
 * Calculate the paginated subset of all data
 * 
 * @returns { Number }
 */
users () {
    if (!this.allUsers.length) {
        return [];
    }
    const offset = (this.page-1) * this.numPerPage;
    const total = offset + this.numPerPage;
    return this.allUsers.slice(offset, total);
}
```

The `pageCount` method is a computed property that tells us the maximum number of pages that are available.

The `users` method is a computed property that takes a _subset_ of `this.allUsers` which we populated asynchronously with a giant pack of data. Instead of iterating over `allUsers`, we actually want to iterate over a subset of it - `users`. The offsets are calculated using the contents of the browser history via `window.onpopstate`.

In `user-list.vue`,

```html
<li v-for="(user, index) in this.users" :key="index">
    =======
</li>

<li v-for="user in this.users">
    >>>>>>> 16dbe5c93d... add in the four grunt packages and rename each to X.bundle.js [SA-11796] ...
</li>
```

## 8.4 Use the Paginate component

At the bottom of `user-list.vue` w can add the `Paginate` component which will do all the heavy lifting for us.

```html
<div v-if="loading">
    <h2>Loading data, wait</h2>
</div>
<div v-else>
    <ul class="information-list">
        <li v-for="(user, index) in users" :key="index">
            <single-user
                :fullname="user.fullname"
                :firstname="user.firstname"
                :lastname="user.lastname"
                :role="user.role"
                :route="user.route"
                :nameLength="nameLength(user.fullname)"
            >
            </single-user>
        </li>
    </ul>
    <vuejs-paginate
        v-model="page"
        :page-count="pageCount"
        :container-class="'pagination'"
        :prev-text="'‹'"
        :next-text="'›'"
        :prev-link-class="'arrow'"
        :next-link-class="'arrow'"
        :disabled-class="'unavailable'"
        :click-handler="onPaginate"
    ></vuejs-paginate>
</div>
```

Recompile and reload; you'll have an asynchronously-powered paginated data set that is fully navigable and simulates browser history, along with user-friendly indicators for loading data and a snappy perceived response because we loaded everything and now it's rendering data locally in the browser. Data is produced by a highly-cohesive decoupled class which can be both unit tested and used in a different context (e.g. phplib/twig instead of vue) without having to copypaste a lot.

> Don't forget to dress it up so it looks nice by using our regular styles and so on.

