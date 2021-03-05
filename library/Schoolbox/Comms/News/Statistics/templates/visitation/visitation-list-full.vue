<script>
    
import { VueGoodTable } from 'vue-good-table';
import { fetchAsJson } from '@shared/fetch/fetch';
import { ENDPOINTS } from '@schoolboxCommsNews/Statistics/templates/visitation/endpoints.js';
import { format, parseISO, formatDistance } from 'date-fns';

export default {

    name: "visitation-list-full",

    components: {
        VueGoodTable
    },   

    props: {
        payload: {
            type: Object,
            required: true
        }
    },

    data () {

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
            ]

        }

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
                    this.loading = false;

                }).catch((error) => {
                    this.loading = false; //display error note in the DOM
                }); 

        },

        /**
         * display the date in the Numerical Date Format within settings
         *
         */
        formatLastVisited (value) {
            const nice = format(parseISO(value), 'dd/LL/yyyy');
            const relative = formatDistance(parseISO(value), new Date(), { addSuffix: true });
            return nice + ' (' + relative + ')';
        },
        
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

