<script>
import axios from 'axios';
import filesize from 'filesize';
import RevealModal from '@schoolbox/reveal-modal';
import Toast from '@schoolbox/toast';

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
        RevealModal,
        Toast,
        // VueGoodTable
    },

    /**
     * Data.
     * Mutable properties that can be accessed from within this component via "this" keyword
     * Note that v-if and v-for must never be on the same node together, as this overrides
     * the meaning of "this" in a way you don't expect.
     */
    data () {
        return {

            /* True, when data is currently loading; used to show skeleton
             */
            loading: true,

            /* All the data for this visualisation
             */
            allData: [],

            /* Table columns. 
             */
            columns: [ 'name', 'size', 'folder', 'location', 'id' ],

            /* vue-tables-2 options, see https://matanya.gitbook.io/vue-tables-2/options-api
             */
            options: {
                headings: {
                    name: 'Filename',
                    size: 'Filesize',
                    folder: 'Folder',
                    location: 'Location',
                    id: 'Delete',
                },
                orderBy: {
                    ascending: true,
                    column: 'name' 
                },
                pagination: {
                    edge: false,
                },
                perPage: 50, 
                perPageValues: [50, 100, 200],
                texts: {
                    count: 'Showing {from} to {to} of {count} files|{count} files|1 file'
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

            /* When you click the Delete link, remember its url
             * A singleton modal spawns and uses this as its confirmation button destination
             * Because of the way the reveal-modal works, you don't pass it an arg
             */
            fileUrlToDelete: undefined,
            isProcessActive: false,
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
        },

        /**
         * Open the Delete File confirmDialog equivalent
         *
         * @param { String }
         */
        showDeleteModal (url) {
            this.fileUrlToDelete = url;
            this.$refs.deleteModal.open();
        },

        /**
         * Asynchronously access an endpoint to delete a file from the appropriate place
         */
        async deleteFile () {

            /* Set the loading flag to prevent people spamming the button
             */
            this.isProcessActive = true;

            try {

                /* Asynchronously access the endpoint to delete the thing
                */
                await axios.get(this.fileUrlToDelete);

                /* Remove the element from the allData stack
                 */
                this.allData = this.allData.filter( (item) => item._links.delete !== this.fileUrlToDelete );

                /* Shut the modal
                 */
                this.$refs.deleteModal.close();

                /* Toast (though I prefer our twig toast until a better replacement comes along)
                 */
                this.$refs.toast.timeout('File deleted', true);
                this.isProcessActive = false;

            } catch (error) {

                /* Deal with error case
                 */
                this.$refs.toast.timeout('Unable to delete file', false);
                this.isProcessActive = false;

            }
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
                <template v-slot:name="props"> 
                    <a :href="props.row._links.send">{{ props.row.name }}</a>
                </template>
                <template v-slot:size="props"> 
                    {{ getHumanReadableFilesize(props.row.size) }}
                </template>
                <template v-slot:folder="props"> 
                    <a :href="`/homepage/${props.row.folderId}`">{{ props.row.folder }}</a>
                </template>
                <template v-slot:id="props"> 
                    <a :href="props.row._links.delete" @click.prevent="showDeleteModal(props.row._links.delete)">Delete</a>
                </template>
            </v-client-table>
        </template>
        <toast ref="toast"></toast>
        <reveal-modal ref="deleteModal">
            <h2>Please confirm</h2>
            <p>Are you sure you want to delete this file?</p>
            <ul class="flex-list buttons">
                <li><a @click.prevent="deleteFile" class="button submit" id="usage-file-delete-button" :disabled="isProcessActive">Delete</a></li>
                <li><a @click.prevent="$refs.deleteModal.close" class="button" id="usage-file-cancel-button" :disabled="isProcessActive">Cancel</a></li>
            </ul>
        </reveal-modal>
    </div>
</template>

<style lang="scss">
@import '@schoolbox/vueTables2/vueTables2';
</style>
