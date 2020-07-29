<script>
    import axios from "axios";
    import {ENDPOINTS} from '@schoolboxCommsNews/Statistics/templates/visitation/endpoints.js';

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
                'loading': true,

                /* The list of users who visited this article, obtained asynchronously
                 */
                'users': []
            }
        },

        /**
         * Mounted.
         * Basically, when vue has done all its inits and is ready to go
         * kind of like window.onload
         */
        mounted: function () {
            let self = this;

            /* Show skeleton
             */
            this.loading = true;

            /* Fetch payload asynchronously
             */
            let request = {
                url: ENDPOINTS.payloadTop10Path(this.payload.article.id),
                method: 'get',
                responseType: 'json'
            }

            axios.request(request)
                    .then(function (response) {

                        /* Load into vue component
                         */
                        self.users = response.data;
                    })
                    .catch(error => {
                        if (error.response) {
                            self.loading = false;
                        }
                    })
                    .then(function() {

                        /* Hide skeleton
                         */
                        self.loading = false;
                    });
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
                    <ul class="grid small-block-grid-1 medium-block-grid-2">
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
                            <li v-for="user in this.users">
                                <div class="card small-12">
                                    <template v-if="user.occursAt">
                                        <a :href="'/search/user/' + user.id" :title="user.fullname + ' (last visited ' + user.occursAt.relative + ')'">
                                            <img :src="'/portrait.php?userId=' + user.id + '&size=square64'" alt="View Profile" />

                                            <p>{{ user.fullname }}</p>
                                        </a>
                                    </template>
                                    <template v-else>
                                        <a :href="'/search/user/' + user.id" :title="user.fullname">
                                            <img :src="'/portrait.php?userId=' + user.id + '&size=square64'" alt="View Profile" />

                                            <p>{{ user.fullname }}</p>
                                        </a>
                                    </template>
                                    <p class="meta">{{ user.role }}</p>
                                </div>
                            </li>
                        </template>
                    </ul>
                    <div class="component-action">
                        <section>
                            <a href="#" class="button">View all</a>
                        </section>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
