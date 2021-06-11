<script>
    
import axios from 'axios';
import { ENDPOINTS } from '@schoolboxCommsNews/Statistics/templates/visitation/endpoints.js';

export default {

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
            columns: ['fullname', 'role', 'occursAt'],
            options: {
                headings: {
                    fullname: 'Name',
                    role: 'Role',
                    occursAt: 'Last Visited',
                },
                orderBy: {
                    ascending: false,
                    column: 'occursAt' //initial sorting display
                },
                customSorting: {
                    fullname: (ascending) => { //sort fullname by last name
                        return (a, b) => {
                            if (ascending) {
                                return a.lastname >= b.lastname ? 1 : -1;
                            }
                            return a.lastname <= b.lastname ? 1 : -1;
                        }
                    }
                },
                pagination: {
                    edge: false,
                },
                perPage: 50, 
                perPageValues: [50, 100, 200],
                texts: {
                    filterPlaceholder: 'Search for visitors...',
                    noResults: 'No visitors found',
                    count: 'Showing {from} to {to} of {count} visitors|{count} visitors|1 visitor'
                },
                skin: 'table-striped table-bordered table-hover',
                sortIcon: {
                    is: 'glyphicon-sort',
                    base: 'glyphicon',
                    up: 'glyphicon-chevron-up',
                    down: 'glyphicon-chevron-down'
                },
                debounce: 100, //faster debouce as data is stored on the client side,
                resizableColumns: false,
                tabbable: false
            },
            rows: [] //Will hold the row data once it's loaded
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
        async loadRows () {
            try {
                const response = await axios.get(ENDPOINTS.payloadFullPath(this.payload.article.id));
                this.rows = response.data;
                this.isLoading = false;
            } catch (error) {
                this.isLoading = false; 
            }
        },

        /**
         * format the ISO date for better readability
         *          
         * @returns {String} - eg: Mar 19, 2021 08:52am (4 minutes ago)
         */
        formatLastVisited (value) {
            return value.nice + ' (' + value.relative + ')';
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
                        <v-client-table 
                            :data="rows" 
                            :columns="columns" 
                            :options="options" 
                        >
                            <template v-slot:fullname="props"> 
                                <a :href="`/search/user/${props.row.id}`" :title="props.row.fullname">{{props.row.fullname}}</a>
                            </template>
                            <template v-slot:occursAt="props"> 
                                <span v-if="props.row.occursAt">{{ formatLastVisited(props.row.occursAt) }}</span>
                            </template>
                        </v-client-table>
                    </section>
                </template>
            </div>
        </div>
    </div>
</template>

<style lang="scss">

@import '@schoolbox/vueTables2/vueTables2';

.VueTables__search-field {
    padding: 10px;
}

</style>

