<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
import { humanFileSize } from "@js/util/fileUtils";
import RevealModal from "@schoolboxVue/components/reveal-modal";
import Toast from "@schoolboxVue/components/toast";
import footerUI from "@schoolboxVue/components/vueTables2/mixinFooterUI";

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
    mixins: [
        footerUI, //to place and keep the tables UI in the footer
    ],

    /**
     * Props.
     * Immutable props that were passed to this component, usually via twig, in our setup.
     * These are the hyphen-separated vars which are magically computed into camelcase elsewhere.
     */
    props: {
        endpoint: {
            type: String,
            required: true,
        },
    },

    /**
     * Components.
     * Any other vue components you also want to use here; they must already be imported
     * at the top of the file.
     */
    components: {
        RevealModal,
        Toast,
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

            /* Table columns.
             */
            columns: ["name", "size", "folder", "location", "id"],

            /* vue-tables-2 options, see https://matanya.gitbook.io/vue-tables-2/options-api
             */
            options: {
                headings: {
                    name: "Filename",
                    size: "Filesize",
                    folder: "Folder",
                    location: "Location",
                    id: "Delete",
                },
                orderBy: {
                    ascending: true,
                    column: "name",
                },
                perPage: 50,
                perPageValues: [50, 100, 200],
                debounce: 100, //faster debouce as data is stored on the client side,
                resizableColumns: false,
                tabbable: false,
                texts: {
                    filterPlaceholder: "Search for files...",
                    noResults: "No matching files...",
                    count: "{from}-{to} of {count}",
                    limit: "Rows per page:",
                },
                sortIcon: {
                    is: "glyphicon-sort",
                    base: "glyphicon",
                    up: "glyphicon-chevron-up",
                    down: "glyphicon-chevron-down",
                },
                customSorting: {
                    size: (ascending) => {
                        //ensure filesize is sorted numerically
                        return (a, b) => {
                            if (ascending) {
                                return parseInt(a.size) >= parseInt(b.size) ? 1 : -1;
                            }
                            return parseInt(a.size) <= parseInt(b.size) ? 1 : -1;
                        };
                    },
                },
            },

            /* When you click the Delete link, remember its url
             * A singleton modal spawns and uses this as its confirmation button destination
             * Because of the way the reveal-modal works, you don't pass it an arg
             */
            fileUrlToDelete: undefined,
            isProcessActive: false,
        };
    },

    /**
     * Mounted.
     * Basically, when vue has done all its inits and is ready to go
     * kind of like window.onload
     */
    mounted() {
        this.loadRows();
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
        async loadRows() {
            this.loading = true;
            try {
                const response = await fetch(this.endpoint);
                this.allData = await response.json();
            } catch (error) {
                console.warn(error);
            } finally {
                this.loading = false;
            }
        },

        /**
         * To update the table to show the UI in the footer
         *
         */
        onLoaded() {
            this.$nextTick(() => this.footerUI_update());
        },

        /**
         * To convert the bytes into a more readable string
         *
         * @param { Number } - 214430838
         *
         * @returns { String } - 204.5 MB
         */
        getHumanReadableFilesize(value) {
            return humanFileSize(value);
        },

        /**
         * Open the Delete File confirmDialog equivalent
         *
         * @param { String }
         */
        showDeleteModal(url) {
            this.fileUrlToDelete = url;
            this.$refs.deleteModal.open();
        },

        /**
         * Asynchronously access an endpoint to delete a file from the appropriate place
         */
        async deleteFile() {
            /* Set the loading flag to prevent people spamming the button
             */
            this.isProcessActive = true;

            try {
                /* Asynchronously access the endpoint to delete the thing
                 */
                await fetch(this.fileUrlToDelete);

                /* Remove the element from the allData stack
                 */
                this.allData = this.allData.filter((item) => item._links.delete !== this.fileUrlToDelete);

                /* Shut the modal
                 */
                this.$refs.deleteModal.close();

                /* Toast (though I prefer our twig toast until a better replacement comes along)
                 */
                this.$refs.toast.timeout("File deleted", true);
                this.isProcessActive = false;
            } catch (error) {
                /* Deal with error case
                 */
                this.$refs.toast.timeout("Unable to delete file", false);
                this.isProcessActive = false;
            }
        },
    },
};
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
            <v-client-table :data="allData" :columns="columns" :options="options" @loaded="onLoaded">
                <template #name="props">
                    <a :href="props.row._links.send">{{ props.row.name }}</a>
                </template>
                <template #size="props">
                    {{ getHumanReadableFilesize(props.row.size) }}
                </template>
                <template #folder="props">
                    <a :href="`/homepage/${props.row.folderId}`">{{ props.row.folder }}</a>
                </template>
                <template #id="props">
                    <a
                        v-if="props.row._links.delete"
                        :href="props.row._links.delete"
                        @click.prevent="showDeleteModal(props.row._links.delete)"
                        >Delete</a
                    >
                </template>
                <template #afterTable>
                    <div class="VueTables__table-footer" ref="tableFooter"></div>
                </template>
            </v-client-table>
        </template>
        <toast ref="toast"></toast>
        <reveal-modal ref="deleteModal">
            <h2>Please confirm</h2>
            <p>Are you sure you want to delete this file?</p>
            <ul class="flex-list buttons">
                <li>
                    <button
                        @click.prevent="deleteFile"
                        class="button submit"
                        id="usage-file-delete-button"
                        :disabled="isProcessActive"
                    >
                        Delete
                    </button>
                </li>
                <li>
                    <button
                        @click.prevent="$refs.deleteModal.close"
                        class="button"
                        id="usage-file-cancel-button"
                        :disabled="isProcessActive"
                    >
                        Cancel
                    </button>
                </li>
            </ul>
        </reveal-modal>
    </div>
</template>

<style lang="scss">
@import "@schoolboxVue/components/vueTables2/vueTables2";
</style>
