<script setup lang="ts">
import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import vSelect from "vue-select";
import debounce from "lodash/debounce";
import "vue-select/dist/vue-select.css";
import Deselect from "@schoolboxVue/components/selectize/Deselect";
import Toast from "@schoolboxVue/components/toast.vue";

vSelect.props.components.default = () => ({ Deselect });

// The number of milliseconds to wait between the user's input and a search for
// users.
const SEARCH_RESULT_RETRIEVE_DELAY = 350;

interface Props {
    getListEndpoint: string; // The endpoint for fetching the list of items.
    postItemEndpoint: string; // The endpoint for posting a new item to.
    userSearchEndpoint: string; // The endpoint to which user search requests are submitted.
    // userSearchQueryParam: string; // The query string parameter to submit the user search parameter within, when performing a user search.
    // userSearchAdditionalParams: {
    //         // Any additional query string parameters to submit to the user
    //         // search endpoint.
    //         //
    //         // By default, this user search show staff users only.
    //         type: Object,
    //         default: () => {
    //             return { filter: JSON.stringify({ roleType: "staff" }) };
    //         },
    //     },
}
const props = defineProps<Props>();

const isDataLoaded = window.Vue.ref(false);
const hasDataLoadError = window.Vue.ref(false);
const loadedData = window.Vue.ref([]);
const addingItem = window.Vue.ref({});
const modifyingItem = window.Vue.ref({});
const regeneratingItem = window.Vue.ref({});
const deletingItem = window.Vue.ref({});
const searchUsers = window.Vue.ref([]);
const saveError = window.Vue.ref(null);
const userSearchQueryParam = window.Vue.ref("");
const userSearchAdditionalParams = window.Vue.ref({ filter: JSON.stringify({ roleType: "staff" }) });

/**
 *
 */
async function getData() {
    try {
        const response = await fetch(props.getListEndpoint);
        loadedData.value = await response.json();
    } catch (error) {
        console.warn(error);
        hasDataLoadError.value = true; // show load error
    } finally {
        isDataLoaded.value = true; // update the display to show the list
    }
}

/**
 *
 */
function showAddModal() {
    saveError.value = null;
    addingItem.value = {};
    // this.$refs.addItemModal.open();
}

/**
 *
 */
async function addItem() {
    try {
        // this.$refs.toast.activate("Creating...");
        const response = await fetch(props.postItemEndpoint, {
            method: "POST",
            body: JSON.stringify(addingItem.value),
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
            },
        });
        if (response.ok) {
            const addedItem = await response.json();
            addingItem.value = addedItem;
            // this.$refs.addItemModal.close();
            // this.$refs.itemAddedModal.open();
            loadedData.value.push({ ...addedItem });
            // this.$refs.toast.timeout("Created", true);
        } else {
            const error = await response.json();
            if (error.title) {
                saveError.value = error.title;
            }
            // this.$refs.toast.timeout("Could not create", false);
        }
    } catch (error) {
        // this.$refs.toast.timeout("Could not create", false);
    }
}

/**
 *
 */
function showModifyModal(item) {
    saveError.value = null;
    modifyingItem.value = { ...item };
    // this.$refs.modifyItemModal.open();
}

/**
 *
 */
async function modifyItem(item) {
    try {
        // this.$refs.toast.activate("Modifying...");
        const response = await fetch(item._links.patch, {
            method: "PATCH",
            body: JSON.stringify(item),
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
            },
        });
        if (response.ok) {
            // this.$refs.modifyItemModal.close();
            // this.$refs.toast.timeout("Modified", true);

            // Replace the existing modified item with the new version
            const modifiedItem = await response.json();
            loadedData.value = loadedData.value.map((v) => (v.id === modifiedItem.id ? modifiedItem : v));
        } else {
            const error = await response.json();
            if (error.title) {
                saveError.value = error.title;
            }
            // this.$refs.toast.timeout("Could not modify", false);
        }
    } catch (error) {
        // this.$refs.toast.timeout("Could not modify", false);
    }
}

/**
 *
 */
function showRegenerateModal(item) {
    saveError.value = null;
    regeneratingItem.value = { ...item };
    // this.$refs.regenerateItemModal.open();
}

/**
 *
 */
async function regenerateItem(item) {
    try {
        // this.$refs.toast.activate("Regenerating...");
        const response = await fetch(item._links.regenerate, {
            method: "POST",
            headers: {
                Accept: "application/json",
            },
        });
        if (response.ok) {
            regeneratingItem.value = await response.json();
            // this.$refs.regenerateItemModal.close();
            // this.$refs.itemRegeneratedModal.open();
            // this.$refs.toast.timeout("Regenerated", true);
        } else {
            const error = await response.json();
            if (error.title) {
                saveError.value = error.title;
            }
            // this.$refs.toast.timeout("Could not regenerate", false);
        }
    } catch (error) {
        // this.$refs.toast.timeout("Could not regenerate", false);
    }
}

/**
 *
 */
function showDeleteModal(item) {
    deletingItem.value = { ...item };
    // this.$refs.deleteItemModal.open();
}

/**
 *
 */
async function deleteItem(item) {
    try {
        await fetch(item._links.delete, {
            method: "DELETE",
        });
        // this.$refs.deleteItemModal.close();
        loadedData.value = loadedData.value.filter((v) => v.id !== item.id);

        // Show toast
    } catch (error) {
        console.warn(error);
    }
}

/**
 * Search callback for the user selectize.
 * This method is debounced so that the search doesn't trigger until
 * the user has stopped typing for `SEARCH_RESULT_RETRIEVE_DELAY`
 * milliseconds.
 * @param {string} query -The search string
 * @param {Function} loading - A function to call when the loading state changes (accepts a Boolean)
 */
const onUserSearch = debounce((query: string, loading: (isLoading: boolean) => void) => {
    if (query.length < 3) {
        return;
    }
    loading(true);

    const searchUrl = new URL(props.userSearchEndpoint, window.location);
    const searchParams = userSearchAdditionalParams.value;
    searchParams[userSearchQueryParam.value] = query;
    searchUrl.search = new URLSearchParams(searchParams);
    console.log(searchUrl);
    // const response = await fetch(searchUrl);
    // const responseJson = await response.json();
    // searchUsers.value = responseJson.data;
    // loading(false);
    fetch(searchUrl)
        .then((response) => response.json())
        .then((data) => {
            searchUsers.value = data;
        })
        .finally(() => {
            loading(false);
        })
        .catch(() => {
            // console.warn(error);
            // hasLoadError.value = true;
        });
}, SEARCH_RESULT_RETRIEVE_DELAY);

window.Vue.onMounted(() => {
    void getData();
});
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
                                <a v-if="row.clientCredentialsUser" :href="row.clientCredentialsUser._links.profile">{{
                                    row.clientCredentialsUser.fullName
                                }}</a>
                                <em v-else>No user</em>
                            </td>
                            <td>
                                <a
                                    href="#"
                                    class="icon-modify"
                                    title="Modify this client"
                                    @click.prevent="showModifyModal(row)"
                                ></a>
                                <a
                                    href="#"
                                    class="icon-refresh"
                                    title="Regenerate this client&rsquo;s secret"
                                    @click.prevent="showRegenerateModal(row)"
                                ></a>
                                <a
                                    href="#"
                                    class="icon-delete"
                                    title="Delete this client"
                                    @click.prevent="showDeleteModal(row)"
                                ></a>
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

        <loading-table v-else />

        <reveal-modal ref="addItemModal">
            <h2>Add oAuth Client</h2>
            <div v-if="saveError" data-alert="" class="alert-box alert">{{ saveError }}</div>
            <label for="addItem-name" class="required">Name</label>
            <p class="meta">
                A unique name for this client: this name will be displayed to a Schoolbox user when they are asked
                whether they wish to share their details.
            </p>
            <input id="addItem-name" v-model="addingItem.name" required />

            <label for="addItem-redirectUri" class="required">Redirect URI</label>
            <p class="meta">
                When the user approves a request to share details, where should they be redirected to? (This URI must be
                able to process an oAuth authorization code.)
            </p>
            <input id="addItem-redirectUri" v-model="addingItem.redirectUri" required />

            <label for="addItem-clientCredentialsUser">Client Credentials User</label>
            <p class="meta">
                Any client credentials requests (i.e. requests made by the client backend to this Schoolbox instance,
                without interaction by an actual user) will be authenticated as this user; if no user is set here, this
                client may not use client credentials requests.
            </p>
            <v-select
                v-model="addingItem.clientCredentialsUser"
                label="fullName"
                placeholder="Search for user, or leave empty for no user"
                input-id="addItem-clientCredentialsUser"
                :options="searchUsers"
                class="schoolbox-vue-selectize"
                :clear-search-on-blur="() => true"
                :clear-search-on-select="true"
                :close-on-select="true"
                @search="onUserSearch"
            >
                <template #option="option">
                    <span class="d-center">{{ option.fullName }}</span>
                </template>
                <template #selected-option="option">
                    <span class="selected d-center">{{ option.fullName }}</span>
                </template>
            </v-select>

            <div class="component-action">
                <section>
                    <a href="#add-item" class="button" @click.prevent="addItem">Add</a>
                    <a href="#cancel" class="button" @click.prevent="$refs.addItemModal.close">Cancel</a>
                </section>
            </div>
        </reveal-modal>

        <reveal-modal ref="itemAddedModal">
            <h2>oAuth Client Added</h2>
            <label for="itemAdded-name">Name</label>
            <input id="itemAdded-name" v-model="addingItem.name" readonly />

            <label for="itemAdded-redirectUri">Redirect URI</label>
            <input id="itemAdded-redirectUri" v-model="addingItem.redirectUri" readonly />

            <label for="itemAdded-clientId">Client ID</label>
            <input id="itemAdded-clientId" v-model="addingItem.clientId" readonly />

            <label for="itemAdded-clientSecret">Client Secret</label>
            <input id="itemAdded-clientSecret" v-model="addingItem.clientSecret" readonly />

            <label for="itemAdded-clientCredentialsUser">Client Credentials User</label>
            <select id="itemAdded-clientCredentialsUser" readonly>
                <option v-if="addingItem.clientCredentialsUser">{{ addingItem.clientCredentialsUser.fullName }}</option>
                <option v-else>No user</option>
            </select>

            <div data-alert="" class="alert-box warning">
                You must copy and store the client secret: it will no longer be retrievable after closing this dialog.
            </div>

            <div class="component-action">
                <section>
                    <a href="#close" class="button" @click.prevent="$refs.itemAddedModal.close">Close</a>
                </section>
            </div>
        </reveal-modal>

        <reveal-modal ref="modifyItemModal">
            <h2>Modify oAuth Client</h2>
            <div v-if="saveError" data-alert="" class="alert-box alert">{{ saveError }}</div>
            <label for="modifyItem-name" class="required">Name</label>
            <p class="meta">
                A unique name for this client: this name will be displayed to a Schoolbox user when they are asked
                whether they wish to share their details.
            </p>
            <input id="modifyItem-name" v-model="modifyingItem.name" required />

            <label for="modifyItem-redirectUri" class="required">Redirect URI</label>
            <p class="meta">
                When the user approves a request to share details, where should they be redirected to? (This URI must be
                able to process an oAuth authorization code.)
            </p>
            <input id="modifyItem-redirectUri" v-model="modifyingItem.redirectUri" required />

            <label for="modifyItem-clientCredentialsUser">Client Credentials User</label>
            <p class="meta">
                Any client credentials requests (i.e. requests made by the client backend to this Schoolbox instance,
                without interaction by an actual user) will be authenticated as this user; if no user is set here, this
                client may not use client credentials requests.
            </p>
            <v-select
                v-model="modifyingItem.clientCredentialsUser"
                label="fullName"
                placeholder="Search for user, or leave empty for no user"
                input-id="modifyItem-clientCredentialsUser"
                :options="searchUsers"
                class="schoolbox-vue-selectize"
                :clear-search-on-blur="() => true"
                :clear-search-on-select="true"
                :close-on-select="true"
                @search="onUserSearch"
            >
                <template #option="option">
                    <span class="d-center">{{ option.fullName }}</span>
                </template>
                <template #selected-option="option">
                    <span class="selected d-center">{{ option.fullName }}</span>
                </template>
            </v-select>

            <div class="component-action">
                <section>
                    <a href="#modify" class="button" @click.prevent="modifyItem(modifyingItem)">Modify</a>
                    <a href="#cancel" class="button" @click.prevent="$refs.modifyItemModal.close">Cancel</a>
                </section>
            </div>
        </reveal-modal>

        <reveal-modal ref="regenerateItemModal">
            <h2>Regenerate Client Secret for oAuth Client {{ regeneratingItem.name }}?</h2>
            <div data-alert="" class="alert-box alert" v-if="saveError">{{ saveError }}</div>
            <div data-alert="" class="alert-box warning">
                Regenerating this client&rsquo;s secret will invalidate the configuration of this Schoolbox instance as
                an oAuth Provider within the client application: it will need to be updated with the regenerated secret.
            </div>
            <div class="component-action">
                <section>
                    <a href="#regenerate" class="button" @click.prevent="regenerateItem(regeneratingItem)"
                        >Regenerate</a
                    >
                    <a href="#cancel" class="button" @click.prevent="$refs.regenerateItemModal.close">Cancel</a>
                </section>
            </div>
        </reveal-modal>

        <reveal-modal ref="itemRegeneratedModal">
            <h2>Client Secret for oAuth Client {{ regeneratingItem.name }} Regenerated</h2>

            <label for="itemRegenerated-clientSecret">Client Secret</label>
            <input id="itemRegenerated-clientSecret" v-model="regeneratingItem.clientSecret" readonly />

            <div data-alert="" class="alert-box warning">
                You must copy and store the client secret: it will no longer be retrievable after closing this dialog.
            </div>

            <div class="component-action">
                <section>
                    <a href="#close" class="button" @click.prevent="$refs.itemRegeneratedModal.close">Close</a>
                </section>
            </div>
        </reveal-modal>

        <reveal-modal ref="deleteItemModal">
            <h2>Delete oAuth Client {{ deletingItem.name }}?</h2>
            <div class="component-action">
                <section>
                    <a href="#delete" class="button" @click.prevent="deleteItem(deletingItem)">Delete</a>
                    <a href="#cancel" class="button" @click.prevent="$refs.deleteItemModal.close">Cancel</a>
                </section>
            </div>
        </reveal-modal>

        <toast ref="toast"></toast>
    </div>
</template>

<style scoped lang="scss"></style>
