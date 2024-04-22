<script>
import RevealModal from "@schoolboxVue/components/reveal-modal.vue";

// Vue-select for selecting a user
import vSelect from "vue-select";
import debounce from "lodash/debounce";
import "vue-select/dist/vue-select.css";
import Deselect from "@schoolboxVue/components/selectize/Deselect";
vSelect.props.components.default = () => ({ Deselect });

// The number of milliseconds to wait between the user's input and a search for
// users.
const SEARCH_RESULT_RETRIEVE_DELAY = 350;

export default {
    components: {RevealModal, vSelect},
    props: {
        getListEndpoint: {
            // The endpoint for fetching the list of items.
            type: String,
            required: true,
        },
        postItemEndpoint: {
            // The endpoint for posting a new item to.
            type: String,
            required: true,
        },
        userSearchEndpoint: {
            // The endpoint to which user search requests are submitted.
            type: String,
            required: true,
        },
        userSearchQueryParam: {
            // The query string parameter to submit the user search parameter
            // within, when performing a user search.
            type: String,
            default: "search",
        },
        userSearchAdditionalParams: {
            // Any additional query string parameters to submit to the user
            // search endpoint.
            //
            // By default, this user search show staff users only.
            type: Object,
            default: () => {
                return { filter: JSON.stringify({roleType: 'staff'}) };
            },
        },
    },

    mounted() {
        this.getData();
    },

    data() {
        return {
            isDataLoaded: false,
            hasDataLoadError: false,
            loadedData: [],
            addingItem: {},
            modifyingItem: {},
            deletingItem: {},
            searchUsers: [],
        };
    },

    methods: {
        async getData() {
            try {
                const response  = await fetch(this.getListEndpoint);
                this.loadedData = await response.json();
            } catch (error) {
                console.warn(error);
                this.hasDataLoadError = true; //show load error
            } finally {
                this.isDataLoaded = true; //update the display to show the list
            }
        },
        showAddModal() {
            this.addingItem = {};
            this.$refs.addItemModal.open();
        },
        async addItem() {
            try {
                const response = await fetch(
                    this.postItemEndpoint,
                    {
                        method: 'POST',
                        body: JSON.stringify(this.addingItem),
                        headers: {
                            "Content-Type": "application/json",
                        },
                    }
                );
                const addedItem = await response.json();
                this.addingItem = addedItem;
                this.$refs.addItemModal.close();
                this.$refs.itemAddedModal.open();
                this.loadedData.push({...addedItem});

                // Show toast
            } catch (error) {
                console.warn(error);
            }
        },
        showModifyModal(item) {
            this.modifyingItem = {...item};
            this.$refs.modifyItemModal.open();
        },
        async modifyItem(item) {
            try {
                const response = await fetch(
                    item._links.patch,
                    {
                        method: 'PATCH',
                        body: JSON.stringify(item),
                        headers: {
                            "Content-Type": "application/json",
                        },
                    }
                );
                this.$refs.modifyItemModal.close();

                // Replace the existing modified item with the new version
                const modifiedItem = await response.json();
                this.loadedData  = this.loadedData.map(
                    v => v.id === modifiedItem.id ? modifiedItem : v
                );

                // Show toast
            } catch (error) {
                console.warn(error);
            }
        },
        showDeleteModal(item) {
            this.deletingItem = {...item};
            this.$refs.deleteItemModal.open();
        },
        async deleteItem(item) {
            try {
                await fetch(
                    item._links.delete,
                    {
                        method: 'DELETE'
                    }
                );
                this.$refs.deleteItemModal.close();
                this.loadedData = this.loadedData.filter(v => v.id !== item.id);

                // Show toast
            } catch (error) {
                console.warn(error);
            }
        },

        /**
         * Search callback for the user selectize.
         *
         * This method is debounced so that the search doesn't trigger until
         * the user has stopped typing for `SEARCH_RESULT_RETRIEVE_DELAY`
         * milliseconds.
         *
         * @param {String}   query   The search string
         * @param {Function} loading A function to call when the loading state
         *                           changes (accepts a Boolean)
         */
        onUserSearch: debounce(
            async function (query, loading) {
                if (query.length < 3) {
                    return;
                }
                loading(true);

                let searchUrl = new URL(this.userSearchEndpoint, window.location),
                    searchParams = this.userSearchAdditionalParams;
                searchParams[this.userSearchQueryParam] = query;
                searchUrl.search = new URLSearchParams(searchParams);
                console.log(searchUrl);
                const response = await fetch(searchUrl);
                const responseJson = await response.json();
                this.searchUsers = responseJson.data;
                loading(false);
            },
            SEARCH_RESULT_RETRIEVE_DELAY,
        ),
    }
}
</script>

<template>
    <div>
        <div v-if="isDataLoaded">
            <div class="small-12 island">
                <template v-if="loadedData.length === 0">
                    <div id="listEmptyState" class="empty-state">
                        <i class="icon-list"></i>
                        <p>
                            No oAuth clients have been configured.
                            <a href="#" @click.prevent="$refs.addItemModal.open">Add an oAuth client</a>.
                        </p>
                    </div>
                </template>
                <template v-else>
                    <table class="no-margin">
                        <tr>
                            <th>Name</th>
                            <th>Redirect URI</th>
                            <th>Client ID</th>
                            <th>Client Credentials User</th>
                            <th>&nbsp;</th>
                        </tr>
                        <tr v-for="row in loadedData">
                            <td>{{ row.name }}</td>
                            <td>{{ row.redirectUri }}</td>
                            <td>{{ row.clientId }}</td>
                            <td>
                                <a v-if="row.clientCredentialsUser" :href="row.clientCredentialsUser._links.profile">{{ row.clientCredentialsUser.fullName }}</a>
                                <em v-else>No user</em>
                            </td>
                            <td>
                                <a href="#" class="icon-modify" title="Modify this client" @click.prevent="showModifyModal(row)"></a>
                                <a href="#" class="icon-delete" title="Delete this client" @click.prevent="showDeleteModal(row)"></a>
                            </td>
                        </tr>
                    </table>
                    <div class="component-action">
                        <section>
                            <a class="button" href="#" @click.prevent="showAddModal">Add oAuth Client</a>
                        </section>
                    </div>
                </template>
            </div>
        </div>

        <div v-else-if="hasDataLoadError">
            <div class="small-12 island">
                <section class="content">
                    <div data-alert class="alert-box alert">
                        Sorry, we are currently unable to load this List data. Please contact Schoolbox Support it the
                        problem persists.
                    </div>
                </section>
            </div>
        </div>

        <div v-else>
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
        </div>

        <reveal-modal ref="addItemModal">
            <h2>Add oAuth Client</h2>
            <label for="addItem-name" class="required">Name</label>
            <input id="addItem-name" required v-model="addingItem.name" />

            <label for="addItem-redirectUri" class="required">Redirect URI</label>
            <input id="addItem-redirectUri" required v-model="addingItem.redirectUri" />

            <label for="addItem-clientCredentialsUser">Client Credentials User</label>
            <v-select
                input-id="addItem-clientCredentialsUser"
                :options="searchUsers"
                class="schoolbox-vue-selectize"
                :clear-search-on-blur="() => true"
                :clear-search-on-select="true"
                :close-on-select="false"
                @search="onUserSearch"
                label="fullName"
                placeholder="Search for user, or leave empty for no user"
                v-model="addingItem.clientCredentialsUser"
            >
                <template #option="option">
                <span class="d-center">
                    {{ option.fullName }}
                </span>
                </template>
                <template #selected-option="option">
                <span class="selected d-center">
                    {{ option.fullName }}
                </span>
                </template>
            </v-select>

            <div class="component-action">
                <section>
                    <a class="button" @click.prevent="addItem" href="#">Add</a>
                    <a class="button" @click.prevent="$refs.addItem.close" href="#">Cancel</a>
                </section>
            </div>
        </reveal-modal>

        <reveal-modal ref="itemAddedModal">
            <h2>oAuth Client Added</h2>
            <label for="itemAdded-name">Name</label>
            <input id="itemAdded-name" readonly v-model="addingItem.name" />

            <label for="itemAdded-redirectUri">Redirect URI</label>
            <input id="itemAdded-redirectUri" readonly v-model="addingItem.redirectUri" />

            <label for="itemAdded-clientId">Client ID</label>
            <input id="itemAdded-clientId" readonly v-model="addingItem.clientId" />

            <label for="itemAdded-clientSecret">Client Secret</label>
            <input id="itemAdded-clientSecret" readonly v-model="addingItem.clientSecret" />

            <label for="itemAdded-clientCredentialsUser">Client Credentials User</label>
            <select id="itemAdded-clientCredentialsUser" readonly>
                <option v-if="addingItem.clientCredentialsUser">{{ addingItem.clientCredentialsUser.fullName }}</option>
                <option v-else>No user</option>
            </select>

            <div data-alert="" class="alert-box warning">
                You must copy and store the client secret:
                it will no longer be retrievable after reloading this page.
            </div>

            <div class="component-action">
                <section>
                    <a class="button" @click.prevent="$refs.itemAddedModal.close" href="#">Close</a>
                </section>
            </div>
        </reveal-modal>

        <reveal-modal ref="modifyItemModal">
            <h2>Modify oAuth Client</h2>
            <label for="modifyItem-name" class="required">Name</label>
            <input id="modifyItem-name" required v-model="modifyingItem.name" />

            <label for="modifyItem-redirectUri" class="required">Redirect URI</label>
            <input id="modifyItem-redirectUri" required v-model="modifyingItem.redirectUri" />

            <label for="modifyItem-clientId">Client ID</label>
            <input id="modifyItem-clientId" readonly v-model="modifyingItem.clientId" />

            <label for="modifyItem-clientCredentialsUser">Client Credentials User</label>
            <v-select
                input-id="modifyItem-clientCredentialsUser"
                :options="searchUsers"
                class="schoolbox-vue-selectize"
                :clear-search-on-blur="() => true"
                :clear-search-on-select="true"
                :close-on-select="false"
                @search="onUserSearch"
                label="fullName"
                placeholder="Search for user, or leave empty for no user"
                v-model="modifyingItem.clientCredentialsUser"
            >
                <template #option="option">
                <span class="d-center">
                    {{ option.fullName }}
                </span>
                </template>
                <template #selected-option="option">
                <span class="selected d-center">
                    {{ option.fullName }}
                </span>
                </template>
            </v-select>

            <div class="component-action">
                <section>
                    <a class="button" @click.prevent="modifyItem(modifyingItem)" href="#">Modify</a>
                </section>
            </div>
        </reveal-modal>

        <reveal-modal ref="deleteItemModal">
            <h2>Delete oAuth Client {{ deletingItem.name }}?</h2>
            <div class="component-action">
                <section>
                    <a class="button" @click.prevent="deleteItem(deletingItem)" href="#">Delete</a>
                    <a class="button" @click.prevent="$refs.deleteItemModal.close" href="#">Cancel</a>
                </section>
            </div>
        </reveal-modal>
    </div>
</template>

<style scoped lang="scss">

</style>
