<script>
    
    import { VueGoodTable } from 'vue-good-table';
    import axios from 'axios';
    import { ENDPOINTS } from '@schoolboxCommsNews/Statistics/templates/visitation/endpoints.js';

    export default {

        /**
         * Name.
         * The name of the component that we have here. This name will be used in the container
         * to spawn this component.
         */
        name: "visitation-list-full",

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
            VueGoodTable
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
                loading: true,

                /* The full list of users who visited this article, obtained asynchronously
                 * This stack gets paginated elsewhere
                 */
                allUsers: [],

                /* The current page number
                 */
                page: 1,

                columns: [
                    {
                        label: 'Id',
                        field: 'id',
                        hidden: true
                    },
                    {
                        label: 'Name',
                        field: 'firstname',
                    },
                    {
                        label: 'Role',
                        field: 'role',
                    },
                    {
                        label: 'Last Visited',
                        field: 'occursAt',
                    },
                ]
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
            this.loading = true;

            /* Fetch payload asynchronously
             */
            let request = {
                url: ENDPOINTS.payloadFullPath(this.payload.article.id),
                method: 'get',
                responseType: 'json'
            }

            axios.request(request)
                .then( (response) => {
                    
                    /* Load into vue component
                        */
                    this.rows = response.data;
                })
                .catch(error => {
                    if (error.response) {
                        this.loading = false;
                    }
                })
                .then(() => {

                    /* Hide skeleton
                        */
                    this.loading = false;
                });

        },

        /**
         * Computed properties.
         * Access the results of a function as if they were a property of an object.
         */
        // computed: {

        // },

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
                <template v-if="this.loading">
                    <table class="skeleton">
                        <tbody>
                        <tr v-for="n in 3" :key="n">
                            <td></td>
                        </tr>
                        </tbody>
                    </table>
                </template>
                <template v-else-if="!this.loading && rows.length === 0">
                    <div class="empty-state">
                        <i class="icon-staff"></i>
                        <p>Nobody has visited yet.</p>
                    </div>
                </template>
                <template v-else>
                    <section>
                        <vue-good-table
                            :columns="columns"
                            :rows="rows"
                            :pagination-options="{
                                enabled: true,
                                perPage: 50,
                                perPageDropdown: [50, 100, 200],
                                rowsPerPageLabel: 'Users per page',
                                infoFn: (params) => `${params.firstRecordOnPage} - ${params.lastRecordOnPage} of ${params.totalRecords} Users`,
                            }"
                        />
                    </section>
                </template>
            </div>
        </div>
    </div>
</template>

<style lang="scss">

@import 'vue-good-table/dist/vue-good-table';

.vgt-wrap__footer .footer__row-count__select {
	width: 100px;
	margin: 0;
	padding: 0px 10px;
}

</style>

