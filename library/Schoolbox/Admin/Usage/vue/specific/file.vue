<script>
import axios from 'axios';
import { VueGoodTable } from 'vue-good-table';
import filesize from 'filesize';

export default {

    /**
     * Name.
     * The name of the component that we have here. This name will be used in the container
     * to spawn this component.
     */
    name: "specific-file",

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
    props: [
       'endpoint'
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

            /* All the data for this visualisation
             */
            allData: [],

            /* Table columns. You can define sorting and formatting functions
             * here, but I found the custom slots to work best for sorting on
             * html representations e.g. linked firstname, linked lastname
             */
            columns: [
                {
                    label: 'Filename',
                    field: 'name'
                },
                {
                    label: 'Filesize',
                    field: 'size',
                    type: 'number',
                    formatFn: filesize
                },
                {
                    label: 'Folder',
                    field: 'folder'
                },
                {
                    label: 'Location',
                    field: 'location'
                },
                {
                    label: 'Delete',
                    field: this.linkDelete,
                    html: true,
                    sortable: false
                },
            ],
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
            url: self.endpoint,
            method: 'get',
            responseType: 'json'
        }

        axios.request(request)
            .then(function (response) {

                /* Load into vue component
                 */
                self.allData = response.data;
            })
            .catch(error => {
                if (error.response) {
                    self.loading = false;
                }
            })
            .then(function () {

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

        /**
         * Render a delete link
         * (This needs a confirmDialog equivalent in vue)
         *
         * @param rowObj
         * @returns {string}
         */
        linkDelete(rowObj) {
            return '<a href="'+rowObj._links.delete+'">Delete</a>';
        }
    }
}
</script>
<template>
    <div>
        <template v-if="this.loading">
            <table class="skeleton">
                <thead>
                <tr>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td></td>
                </tr>
                <tr>
                    <td></td>
                </tr>
                </tbody>
            </table>
        </template>
        <template v-else>
            <vue-good-table
                styleClass="vgt-table striped bordered"
                :columns="columns"
                :rows="allData"
                :search-options="{
                    enabled: false
                }"
                :sort-options="{
                    initialSortBy: {
                        field: 'name',
                        type: 'asc'
                    }
                }"
                :pagination-options="{
                    enabled: true,
                    perPage: 50,
                    perPageDropdown: [50, 100, 200],
                    rowsPerPageLabel: 'Files per page',
                    infoFn: (params) => `${params.firstRecordOnPage} - ${params.lastRecordOnPage} of ${params.totalRecords} Files`,
                }"
            >
                <template slot="table-row" slot-scope="props">
                    <template v-if="props.column.field == 'name'">
                        <a :href="props.row._links.send">{{ props.row.name }}</a>
                    </template>
                    <template v-else-if="props.column.field == 'folder'">
                        <a :href="'/homepage/' + props.row.folderId">{{ props.row.folder }}</a>
                    </template>
                    <template v-else-if="props.column.label == 'Delete'">
                        <a :href="props.row._links.delete">Delete</a>
                    </template>
                    <template v-else>
                        {{props.formattedRow[props.column.field]}}
                    </template>
                </template>
            </vue-good-table>
        </template>
    </div>
</template>

<style lang="scss">
@import '@schoolbox/vueGoodTable/vueGoodTable';
</style>
