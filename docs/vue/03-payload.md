# 3. Payload Data

Now we can start getting data into vue. As per multi-layer application architecture (and unlike twig) there's a clear delineation between the business logic layer and the presentation layer when you use a Vue Component.

Vue Components are provided data by using a syntax similar to defining html attributes.

In schoolbox, the backend is responsible for collating a payload of facts that the frontend can render for the user. The exact format of this is largely up to developers to decide, though the idea and intent remains similar.

> Our historical usage of twig allowed frontends to generate what would be classically backend data, leading to deep coupling and really problematic/confusing domain spaces. It's a convenient, though dangerous, aspect to twig.

Let's set up a hardcoded payload for now, to demonstrate usage.

## 3.1 Set up Payload in Container

In `container.twig` we can construct a twig data structure then json_encode it as input to the `user-list` Vue Component.

```html
{% set hardcodedPayload = { 'cat': 'Navarre', 'dog': 'Benji', 'horse': 'Mr. Ed', 'elephant': 'Dumbo' } %}

<div id="user-list-container">
    <user-list :payload="{{ hardcodedPayload | json_encode }}"></user-list>
</div>
```

## 3.2 Access the payload contents in Vue Component

To get to that data, we need to define a `prop` in the Vue Component. In `user-list.vue`,

```js
props: {
    payload: {
        type: Object,
        required: true,
        default: () => {
            return {
                cat: 'cat',
                dog: 'dog',
                horse: 'horse',
                elephant: 'elephant'
            }
        }
    }
},
```

This `payload` connects with the `:payload` attribute on `user-list`. Now we can start rendering its contents. Underneath the placeholder text you can add the following:

```html
<ul>
    <li v-for="(value, index) in payload" :key="index">{{ value }}</li>
</ul>
```

To see the output, `npm run build` and visit the Greenhouse url.

> If you put it in the wrong place, npm will tell you about it when you try to compile.

## 3.3 Add more payload args

You can add more args to provide pieces of info to the `user-list` component. See below for some examples. Non-scalar data should be json_encoded, scalars can be provided as-is; however, note the usage of apostrophes in the `:datetime` attribute below.  Experiment with this idea by modifying your container.twig file so that it looks like below.  The props object in the user-list.vue file will need to be modified also (instructions follow).

```html
<h1>Container for Vue initialisation</h1>
<p>Our vue component gets initialised here</p>

{% set hardcodedPayload = { 'cat': 'Navarre', 'dog': 'Benji', 'horse': 'Mr. Ed', 'elephant': 'Dumbo' } %}
{% set stuartMahon = { 'fullname': 'Stuart Mahon', 'firstname': 'stu', 'lastname': 'mahon' } %}
{% set datetime = 'now'|date('Y-m-d H:i:s') %}

<div id="user-list-container">
    <user-list
        :payload="{{ hardcodedPayload | json_encode }}"
        :stuart-mahon="{{ stuartMahon | json_encode }}"
        :datetime="'{{ datetime }}'"
    ></user-list>
</div>

<!-- You need this; spawns after you "npm run build" or "npm run watch" for the first time -->
<script src="{{ CDN_PREFIX }}/js/bundle/userList.js"></script>
```

> When you pass scalar values, sometimes you need to wrap them inside apostrophes.
>
> `:datetime="some value"` is synonymous with`var datetime = some value` which is syntactically broken; that is why you need apostrophes.
>
> `:datetime="'some value'"` is synonymous with`var datetime = 'some value'`, which works. Keep this in mind because it applies throughout all Vue templates too.

Define them in the `props` object (in the user-list.vue file).
At a minimum, define the type of each property and whether it's required

```js
props: {
    payload: {
        type: Object,
        required: true,
        default: () => {
            return {
                cat: 'cat',
                dog: 'dog',
                horse: 'horse',
                elephant: 'elephant'
            }
        }
    }
    stuartMahon: {
        type: Object,
        required: true
    }
    datetime: {
        type: String,
        required: false
    }
},
```

Then render them out (at the bottom of the user-list.vue page, inside the <template> block):

```html
<p>
    {{ stuartMahon.firstname }} {{ stuartMahon.lastname }} {{ datetime }}
</p>
```

Don't forget that every time you change Vue code, you need to recompile everything or be running npm run watch.

Again, note how the camel-case var `stuartMahon` was derived from the hyphen-separated var `stuart-mahon`.

[Generate Payload](04-generate-payload.md)
