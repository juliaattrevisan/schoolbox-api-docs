<script>

import axios from 'axios';
import filesize from 'filesize';


export default {

    /**
     * Name.
     * The name of the component that we have here. This name will be used in the container
     * to spawn this component.
     */
    name: "user",

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
        endpoint: {
           type: String, 
           required: true
        }
    },

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
            loading: true,

            /* All the data for this visualisation
             */
            allData: [],

            /* Table columns. You can define sorting and formatting functions
             * here, but I found the custom slots to work best for sorting on
             * html representations e.g. linked firstname, linked lastname
             */
            columns: [ 'firstname', 'lastname', 'role', 'size' ],
            options: {
                hiddenColumns: ['id'],
                headings: {
                    firstname: 'Firstname',
                    lastname: 'Lastname',
                    role: 'Role',
                    size: 'Filesize',
                },
                orderBy: {
                    ascending: true,
                    column: 'lastname' 
                },
                pagination: {
                    edge: false,
                },
                perPage: 50, 
                perPageValues: [50, 100, 200],
                texts: {
                    count: 'Showing {from} to {to} of {count} users|{count} users|1 user'
                },
                skin: 'table-striped table-bordered table-hover',
                sortIcon: {
                    is: 'glyphicon-sort',
                    base: 'glyphicon',
                    up: 'glyphicon-chevron-up',
                    down: 'glyphicon-chevron-down'
                },
                resizableColumns: false,
                tabbable: false,
                filterable: false
            },
        }
    },

    /**
     * Mounted.
     * Basically, when vue has done all its inits and is ready to go
     * kind of like window.onload
     */
    mounted () {
        this.loadRows();
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
         * Fetch payload asynchronously
         *
         */
        async loadRows () {
            this.loading = true;
            try {
                const response = await axios.get(this.endpoint);
                this.allData = response.data;
                this.loading = false;
            } catch (error) {
                this.loading = false; 
            }
        },

        /**
         * To convert the bytes into a more readable string
         * 
         * @param { Number } - 214430838
         * 
         * @returns { String } - 204.5 MB
         */
        getHumanReadableFilesize (value) {
            return filesize(value);
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
            <v-client-table 
                :data="allData" 
                :columns="columns" 
                :options="options" 
            >
                <template v-slot:firstname="props"> 
                    <a :href="`/adminv2/usage/specific/${props.row.userId}`" :title="props.row.fullname">{{ props.row.firstname }}</a>
                </template>
                <template v-slot:lastname="props"> 
                    <a :href="`/adminv2/usage/specific/${props.row.userId}`" :title="props.row.fullname">{{ props.row.lastname }}</a>
                </template>
                <template v-slot:size="props"> 
                    {{ getHumanReadableFilesize(props.row.size) }}
                </template>
            </v-client-table>
        </template>
    </div>
</template>

<style lang="scss">
@import '@schoolbox/vueTables2/vueTables2';
</style>
