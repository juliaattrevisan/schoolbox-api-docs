<script>
    
import { VueGoodTable } from 'vue-good-table';
import { fetchAsJson } from '@shared/fetch/fetch';
import { ENDPOINTS } from '@schoolboxCommsNews/Statistics/templates/visitation/endpoints.js';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';

export default {

    name: 'visitation-list-full',

    components: {
        VueGoodTable
    },   

    props: {
        payload: {
            type: Object,
            required: true,
            default: () => {
                return { 
                    article: {
                        id: 0
                    }
                }
            }
        }
    },

    data () {

        return {

            /* True, when data is currently isLoading; used to show skeleton
                */
            isLoading: true,
               
            /* The table headings to dsiplay and thus row data to show
                */
            columns: [
                {
                    label: 'Name',
                    field: 'fullname'
                },
                {
                    label: 'Role',
                    field: 'role'
                },
                {
                    label: 'Last Visited',
                    field: 'occursAt.absolute',
					formatFn: this.formatLastVisited
                },
            ],

            /* Will hold the row data once it's loaded
                */
            rows: []

        }

    },

    created () {
        dayjs.extend(relativeTime);
    },

    mounted () {
        this.loadRows();
    },

    methods: {
        
        /**
         * Fetch payload asynchronously
         *
         */
        loadRows () {

            fetchAsJson(ENDPOINTS.payloadFullPath(this.payload.article.id))
                .then((response) => {
                    
                    /* Load into vue component
                        */
                    this.rows = response;
                    
                    /* Hide skeleton
                        */
                    this.isLoading = false;

                }).catch((error) => {
                    this.isLoading = false; //display error note in the DOM
                }); 

        },

        /**
         * format the ISO date for better readability
         *
         */
        formatLastVisited (value) {
            const nice = dayjs(value).format('MMM D, YYYY hh:mma');
            const relative = dayjs(value).fromNow();
            return nice + ' (' + relative + ')';
        },
        
    }

}

</script>

<template>
    <div>
        <div class="row">
            <div class="small-12 island">
                <template v-if="this.isLoading">
                    <table class="skeleton">
                        <tbody>
                        <tr v-for="n in 3" :key="n">
                            <td></td>
                        </tr>
                        </tbody>
                    </table>
                </template>
                <template v-else-if="!this.isLoading && rows.length === 0">
                    <div class="empty-state">
                        <i class="icon-staff"></i>
                        <p>Nobody has visited yet.</p>
                    </div>
                </template>
                <template v-else>
                    <section>
                        <vue-good-table
                            styleClass="vgt-table striped bordered"
                            :columns="columns"
                            :rows="rows"
                            :search-options="{
                                enabled: true,
                                placeholder: 'Search for users...',
                            }"
                            :sort-options="{
                                initialSortBy: { 
                                    field: 'occursAt.absolute',
                                    type: 'desc'
                                }
                            }"
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

