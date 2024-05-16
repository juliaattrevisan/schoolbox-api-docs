<script setup lang="ts">
import debounce from "lodash/debounce";
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";
import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import Deselect from "@schoolboxVue/components/selectize/Deselect.vue";
import Toast from "@schoolboxVue/components/toast.vue";
import type { OAuthClient } from "../js/types/OAuthClient";

vSelect.props.components.default = () => ({ Deselect });

// The number of milliseconds to wait between the user's input and a search for users.
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
const clientList = window.Vue.ref<OAuthClient[]>([]);
const addingClient = window.Vue.ref<OAuthClient | null>(null);
const modifyingClient = window.Vue.ref<OAuthClient | null>(null);
const regeneratingClient = window.Vue.ref<OAuthClient | null>(null);
const deletingClient = window.Vue.ref<OAuthClient | null>(null);
const searchUsers = window.Vue.ref([]);
const saveError = window.Vue.ref(null);
const userSearchQueryParam = window.Vue.ref("");
const userSearchAdditionalParams = window.Vue.ref({ filter: JSON.stringify({ roleType: "staff" }) });

const addClientModal = window.Vue.ref<typeof RevealModal | null>(null);
const clientAddedModal = window.Vue.ref<typeof RevealModal | null>(null);
const modifyClientModal = window.Vue.ref<typeof RevealModal | null>(null);
const regenerateClientModal = window.Vue.ref<typeof RevealModal | null>(null);
const clientRegeneratedModal = window.Vue.ref<typeof RevealModal | null>(null);
const deleteClientModal = window.Vue.ref<typeof RevealModal | null>(null);

/**
 *
 */
async function getData() {
    try {
        const response = await fetch(props.getListEndpoint);
        clientList.value = (await response.json()) as OAuthClient[];
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
    addingClient.value = null;
    addClientModal.value.open();
}

/**
 * Add another 0Authclient
 */
async function addClient() {
    try {
        // this.$refs.toast.activate("Creating...");
        const response = await fetch(props.postItemEndpoint, {
            method: "POST",
            body: JSON.stringify(addingClient.value),
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
            },
        });
        if (response.ok) {
            const newClient = (await response.json()) as OAuthClient;
            addingClient.value = newClient;
            addClientModal.value.close();
            clientAddedModal.value.open();
            clientList.value.push({ ...newClient });
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
function showModifyModal(client: OAuthClient) {
    saveError.value = null;
    modifyingClient.value = { ...client };
    modifyClientModal.value.open();
}

/**
 *
 */
async function modifyItem(client: OAuthClient) {
    try {
        // this.$refs.toast.activate("Modifying...");
        const response = await fetch(client._links.patch, {
            method: "PATCH",
            body: JSON.stringify(client),
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
            },
        });
        if (response.ok) {
            modifyClientModal.value.close();
            // this.$refs.toast.timeout("Modified", true);

            // Replace the existing modified client with the new version
            const modifiedClient = (await response.json()) as OAuthClient;
            clientList.value = clientList.value.map((c) => (c.id === modifiedClient.id ? modifiedClient : c));
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
function showRegenerateModal(client: OAuthClient) {
    saveError.value = null;
    regeneratingClient.value = { ...client };
    regenerateClientModal.value.open();
}

/**
 *
 */
async function regenerateClient(client: OAuthClient) {
    try {
        // this.$refs.toast.activate("Regenerating...");
        const response = await fetch(client._links.regenerate, {
            method: "POST",
            headers: {
                Accept: "application/json",
            },
        });
        if (response.ok) {
            regeneratingClient.value = (await response.json()) as OAuthClient;
            regenerateClientModal.value.close();
            clientRegeneratedModal.value.open();
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
function showDeleteModal(client: OAuthClient) {
    deletingClient.value = { ...client };
    deleteClientModal.value.open();
}

/**
 *
 */
async function deleteClient(client: OAuthClient) {
    try {
        await fetch(client._links.delete, {
            method: "DELETE",
        });
        deleteClientModal.value.close();
        clientList.value = clientList.value.filter((c) => c.id !== client.id);

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
    // const response = await fetch(searchUrl);
    // const responseJson = await response.json();
    // searchUsers.value = responseJson.data;
    // loading(false);
    fetch(searchUrl)
        .then((response) => response.json())
        .then((jsonResponse) => {
            searchUsers.value = jsonResponse.data;
        })
        .finally(() => {
            loading(false);
        })
        .catch(() => {
            // console.warn(error);
            // hasLoadError.value = true;
        });
}, SEARCH_RESULT_RETRIEVE_DELAY);

/**
 * Load the oauth client config list
 */
window.Vue.onMounted(() => {
    void getData();
});
</script>

<template>
    <div>
        <div v-if="isDataLoaded">
            <div class="small-12 island">
                <template v-if="clientList.length === 0">
                    <div id="listEmptyState" class="empty-state">
                        <i class="icon-list"></i>
                        <p>
                            No oAuth clients have been configured.
                            <a href="#" @click.prevent="addClientModal.open">Add an oAuth client</a>.
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
                        <tr v-for="row in clientList" :key="row.clientId">
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

        <reveal-modal ref="addClientModal">
            <h2>Add oAuth Client</h2>
            <div v-if="saveError" data-alert="" class="alert-box alert">{{ saveError }}</div>
            <label for="addClient-name" class="required">Name</label>
            <p class="meta">
                A unique name for this client: this name will be displayed to a Schoolbox user when they are asked
                whether they wish to share their details.
            </p>
            <input id="addClient-name" v-model="addingClient.name" required />

            <label for="addClient-redirectUri" class="required">Redirect URI</label>
            <p class="meta">
                When the user approves a request to share details, where should they be redirected to? (This URI must be
                able to process an oAuth authorization code.)
            </p>
            <input id="addClient-redirectUri" v-model="addingClient.redirectUri" required />

            <label for="addClient-clientCredentialsUser">Client Credentials User</label>
            <p class="meta">
                Any client credentials requests (i.e. requests made by the client backend to this Schoolbox instance,
                without interaction by an actual user) will be authenticated as this user; if no user is set here, this
                client may not use client credentials requests.
            </p>
            <v-select
                v-model="addingClient.clientCredentialsUser"
                label="fullName"
                placeholder="Search for user, or leave empty for no user"
                input-id="addClient-clientCredentialsUser"
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
                    <a href="#add-item" class="button" @click.prevent="addClient">Add</a>
                    <a href="#cancel" class="button" @click.prevent="addClientModal.close">Cancel</a>
                </section>
            </div>
        </reveal-modal>

        <reveal-modal ref="clientAddedModal">
            <h2>oAuth Client Added</h2>
            <label for="itemAdded-name">Name</label>
            <input id="itemAdded-name" v-model="addingClient.name" readonly />

            <label for="itemAdded-redirectUri">Redirect URI</label>
            <input id="itemAdded-redirectUri" v-model="addingClient.redirectUri" readonly />

            <label for="itemAdded-clientId">Client ID</label>
            <input id="itemAdded-clientId" v-model="addingClient.clientId" readonly />

            <label for="itemAdded-clientSecret">Client Secret</label>
            <input id="itemAdded-clientSecret" v-model="addingClient.clientSecret" readonly />

            <label for="itemAdded-clientCredentialsUser">Client Credentials User</label>
            <select id="itemAdded-clientCredentialsUser" readonly>
                <option v-if="addingClient.clientCredentialsUser">
                    {{ addingClient.clientCredentialsUser.fullName }}
                </option>
                <option v-else>No user</option>
            </select>

            <div data-alert="" class="alert-box warning">
                You must copy and store the client secret: it will no longer be retrievable after closing this dialog.
            </div>

            <div class="component-action">
                <section>
                    <a href="#close" class="button" @click.prevent="clientAddedModal.close">Close</a>
                </section>
            </div>
        </reveal-modal>

        <reveal-modal ref="modifyClientModal">
            <h2>Modify oAuth Client</h2>
            <div v-if="saveError" data-alert="" class="alert-box alert">{{ saveError }}</div>
            <label for="modifyItem-name" class="required">Name</label>
            <p class="meta">
                A unique name for this client: this name will be displayed to a Schoolbox user when they are asked
                whether they wish to share their details.
            </p>
            <input id="modifyItem-name" v-model="modifyingClient.name" required />

            <label for="modifyItem-redirectUri" class="required">Redirect URI</label>
            <p class="meta">
                When the user approves a request to share details, where should they be redirected to? (This URI must be
                able to process an oAuth authorization code.)
            </p>
            <input id="modifyItem-redirectUri" v-model="modifyingClient.redirectUri" required />

            <label for="modifyItem-clientCredentialsUser">Client Credentials User</label>
            <p class="meta">
                Any client credentials requests (i.e. requests made by the client backend to this Schoolbox instance,
                without interaction by an actual user) will be authenticated as this user; if no user is set here, this
                client may not use client credentials requests.
            </p>
            <v-select
                v-model="modifyingClient.clientCredentialsUser"
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
                    <a href="#modify" class="button" @click.prevent="modifyItem(modifyingClient)">Modify</a>
                    <a href="#cancel" class="button" @click.prevent="modifyClientModal.close">Cancel</a>
                </section>
            </div>
        </reveal-modal>

        <reveal-modal ref="regenerateClientModal">
            <h2>Regenerate Client Secret for oAuth Client {{ regeneratingClient.name }}?</h2>
            <div v-if="saveError" data-alert="" class="alert-box alert">{{ saveError }}</div>
            <div data-alert="" class="alert-box warning">
                Regenerating this client&rsquo;s secret will invalidate the configuration of this Schoolbox instance as
                an oAuth Provider within the client application: it will need to be updated with the regenerated secret.
            </div>
            <div class="component-action">
                <section>
                    <a href="#regenerate" class="button" @click.prevent="regenerateClient(regeneratingClient)"
                        >Regenerate</a
                    >
                    <a href="#cancel" class="button" @click.prevent="regenerateClientModal.close">Cancel</a>
                </section>
            </div>
        </reveal-modal>

        <reveal-modal ref="clientRegeneratedModal">
            <h2>Client Secret for oAuth Client {{ regeneratingClient.name }} Regenerated</h2>

            <label for="itemRegenerated-clientSecret">Client Secret</label>
            <input id="itemRegenerated-clientSecret" v-model="regeneratingClient.clientSecret" readonly />

            <div data-alert="" class="alert-box warning">
                You must copy and store the client secret: it will no longer be retrievable after closing this dialog.
            </div>

            <div class="component-action">
                <section>
                    <a href="#close" class="button" @click.prevent="clientRegeneratedModal.close">Close</a>
                </section>
            </div>
        </reveal-modal>

        <reveal-modal ref="deleteClientModal">
            <h2>Delete oAuth Client {{ deletingClient.name }}?</h2>
            <div class="component-action">
                <section>
                    <a href="#delete" class="button" @click.prevent="deleteClient(deletingClient)">Delete</a>
                    <a href="#cancel" class="button" @click.prevent="deleteClientModal.close">Cancel</a>
                </section>
            </div>
        </reveal-modal>

        <toast ref="toast"></toast>
    </div>
</template>

<style scoped lang="scss"></style>
