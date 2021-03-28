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
            isLoading: true, //used to show skeleton
            columns: [ //The table headings to display and thus row data to show
                {
                    label: 'id',
                    field: 'id',
                    hidden: true
                },
                {
                    label: 'Name',
                    field: 'fullname',
                    sortFn: this.sortName
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
            rows: [] //Will hold the row data once it's loaded
        }
    },

    created () {
        dayjs.extend(relativeTime); //add the relativeTime plugin to be able to use fromNow() in formatLastVisited
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
                    this.rows = response; //Load into vue component
                    this.isLoading = false; //Hide skeleton
                
                }).catch((error) => {
                    this.isLoading = false; //display error note in the DOM
                }); 
        },

        /**
         * use the last name for formatting the full name
         * https://xaksis.github.io/vue-good-table/guide/configuration/column-options.html#sortfn
         *
         */
        sortName (x, y, col, rowX, rowY) {
            return (rowX.lastname < rowY.lastname ? -1 : (rowX.lastname > rowY.lastname ? 1 : 0));
        },

        /**
         * format the ISO date for better readability
         * https://xaksis.github.io/vue-good-table/guide/configuration/column-options.html#formatfn
         *          
         * @returns {String} - eg: Mar 19, 2021 08:52am (4 minutes ago)
         */
        formatLastVisited (value) {
            const nice = dayjs(value).format('MMM D, YYYY hh:mma');
            const relative = dayjs(value).fromNow();
            return nice + ' (' + relative + ')';
        }
        
    }

}

</script>

<template>
    <div>
        <div class="row">
            <div class="small-12 island">
                <template v-if="this.isLoading">
                    <section class="content">
                        <div class="loader"></div>
                    </section>
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
                                placeholder: 'Search for visitors...',
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
                        >
                            <div slot="emptystate">
                                <p>No visitors found</p>
                            </div>
                            <template slot="table-row" slot-scope="props">
                                <span v-if="props.column.field === 'fullname'">
                                    <a :href="'/search/user/' + props.row.id" :title="props.row.fullname">{{props.row.fullname}}</a> 
                                </span>
                                <span v-else>
                                    {{ props.formattedRow[props.column.field] }}
                                </span>
                            </template>
                        </vue-good-table>
                    </section>
                </template>
            </div>
        </div>
    </div>
</template>

<style lang="scss">

@import '@schoolbox/vueGoodTable/vueGoodTable';

</style>

