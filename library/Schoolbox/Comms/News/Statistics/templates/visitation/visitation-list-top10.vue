<script>
    import axios from "axios";

    export default {

        /**
         * Name.
         * The name of the component that we have here. This name will be used in the container
         * to spawn this component.
         */
        name: "visitation-list-top10",

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
            'payload',
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

                /* True, when data is currently loading; used to show skeleton
                 */
                'loading': true
            }
        },

        /**
         * Mounted.
         * Basically, when vue has done all its inits and is ready to go
         * kind of like window.onload
         */
        mounted: function () {

            /* Show skeleton
             */

            /* Fetch payload asynchronously
             */

            /* Load into vue component
             */

            /* Hide skeleton
             */
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
        <div class="row">
            <div class="small-12 island">
                <h2 class="subheader">Top 10 Visitors</h2>

                <section>
                    <ul class="information-list">
                        <template v-if="this.loading">
                            <li v-for="n in 3">
                                <div class="list-item">
                                    <div class="small-12 card skeleton">
                                        <div class="img"></div>
                                        <h3></h3>
                                        <p class="meta"></p>
                                    </div>
                                </div>
                            </li>
                        </template>
                        <template v-else>
                            <ul class="information-list">
                                <li v-for="user in this.payload.users">
                                    {{ user.id }} {{ user.role }} {{ user.fullname }}

                                    <template v-if="user.occursAt">
                                        {{ user.occursAt }}
                                    </template>
                                </li>
                            </ul>
                        </template>
                    </ul>
                </section>
            </div>
        </div>
    </div>
</template>
