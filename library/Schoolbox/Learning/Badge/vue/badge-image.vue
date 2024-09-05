<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
export default {
    /**
     * Name.
     * The name of the component that we have here. This name will be used in the container
     * to spawn this component.
     */
    name: "badge-image",

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
     * These are the hyphen-separated vars which are magically computed into camelcase elsewhere.
     */
    props: {
        badge: {
            default: null,
            type: Object,
        },
        css: {
            default: "",
            type: String,
        },
    },

    /**
     * Components.
     * Any other vue components you also want to use here; they must already be imported
     * at the top of the file.
     */
    components: {},

    /**
     * Data.
     * Mutable properties that can be accessed from within this component via "this" keyword
     * Note that v-if and v-for must never be on the same node together, as this overrides
     * the meaning of "this" in a way you don't expect.
     */
    data() {
        return {};
    },

    /**
     * Mounted.
     * Basically, when vue has done all its inits and is ready to go
     * kind of like window.onload
     */
    mounted: function () {},

    /**
     * Computed properties.
     * Access the results of a function as if they were a property of an object.
     */
    computed: {},

    /**
     * Methods.
     * Arbitrary javascript functions which operate on the args provided, just like any
     * other ordinary javascript that you might have written before.
     */
    methods: {},
};
</script>
<template>
    <div>
        <!-- Images representing badges can be a direct path to an svg
          -- or an uploaded image into storage; decide which one based
          -- on which fields were null
          -->
        <template v-if="badge.image">
            <img :src="badge.image._links.image" :alt="badge.name" :title="badge.name" :class="'badge-image ' + css" />
        </template>
        <template v-else>
            <img :src="badge._links.icon" :alt="badge.name" :title="badge.name" :class="'badge-icon ' + css" />
        </template>
    </div>
</template>

<style lang="scss" scoped>
.selected-badge-image {
    //shrink down the image, used by #badge-list-form
    height: auto;
    width: 30px;
}
</style>
