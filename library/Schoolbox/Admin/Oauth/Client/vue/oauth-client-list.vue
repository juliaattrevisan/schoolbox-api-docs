<script setup lang="ts">
/**
 * To manage clients for oAuth
 * Displays a list of clients, allows adding, modifying, regenerating, and deleting clients.
 */

import debounce from "lodash/debounce";
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";
import { activate, timeout } from "@schoolboxVue/components/toast/sbxToastEvent";
import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import Deselect from "@schoolboxVue/components/selectize/Deselect.vue";
import { extractErrorMessage } from "@js/util/sbx/fetchUtils";
import { getEmptyOAuthClient } from "../js/types/OAuthClient";
import { ActionType } from "../js/ActionType";
import type { OAuthClient } from "../js/types/OAuthClient";

// @ts-expect-error - This is expected as per https://vue-select.org/guide/components.html#vue-3x
vSelect.props.components.default = (): void => ({ Deselect });

type VueSelectData = {
    // the loaded user search results
    data: VueSelectOption[];
};
type VueSelectOption = {
    // The type of the options for the user search (what selectize is expecting)
    fullName: string;
};

const SEARCH_RESULT_RETRIEVE_DELAY = 350; // The number of milliseconds to wait between the user's input and a search for users.

interface Props {
    getListEndpoint: string; // The endpoint for fetching the list of items.
    postItemEndpoint: string; // The endpoint for posting a new item to.
    userSearchEndpoint: string; // The endpoint to which user search requests are submitted.
}
const props = defineProps<Props>();

const isDataLoading = window.Vue.ref(true);
const hasDataLoadError = window.Vue.ref(false);
const isProcessing = window.Vue.ref(false);
const clientList = window.Vue.ref<OAuthClient[]>([]);
const addingClient = window.Vue.ref<OAuthClient>(getEmptyOAuthClient());
const modifyingClient = window.Vue.ref<OAuthClient>(getEmptyOAuthClient());
const regeneratingClient = window.Vue.ref<OAuthClient>(getEmptyOAuthClient());
const deletingClient = window.Vue.ref<OAuthClient>(getEmptyOAuthClient());
const searchUsers = window.Vue.ref<VueSelectOption[]>([]);
const saveError = window.Vue.ref<string | null>(null);
const userSearchAdditionalParams = { filter: JSON.stringify({ roleType: "staff" }) };
const currentAction = window.Vue.ref<ActionType>(ActionType.ADD);
const modalEl = window.Vue.ref<typeof RevealModal | null>(null);
const hasNoName = window.Vue.ref(false);
const hasNoRedirect = window.Vue.ref(false);

/**
 * Fetch the current list of clients from the server.
 */
async function getData(): Promise<void> {
    try {
        const response = await fetch(props.getListEndpoint);
        clientList.value = (await response.json()) as OAuthClient[];
    } catch (error) {
        hasDataLoadError.value = true; // show load error
    } finally {
        isDataLoading.value = false; // update the display to show the list
    }
}

/**
 * Show the add modal
 */
function showAddModal(): void {
    saveError.value = null;
    addingClient.value = getEmptyOAuthClient();
    currentAction.value = ActionType.ADD;
    if (modalEl.value) {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call -- RevealModal is untyped
        modalEl.value.open();
    }
}

/**
 * To hide any error messages that may be displayed
 */
function resetValidationChecks(): void {
    hasNoName.value = false;
    hasNoRedirect.value = false;
}

/**
 * Close the modal
 */
function closeModal(): void {
    if (modalEl.value) {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call -- RevealModal is untyped
        modalEl.value.close();
    }
}

/**
 * When the modal is closed
 */
function onModalClosed(): void {
    resetValidationChecks();
}

/**
 * Add another 0AuthClient
 */
async function addClient(): Promise<void> {
    resetValidationChecks();
    if (isProcessing.value) {
        return;
    }
    if (addingClient.value.name === "") {
        hasNoName.value = true;
    }
    if (addingClient.value.redirectUri === "") {
        hasNoRedirect.value = true;
    }
    if (hasNoName.value || hasNoRedirect.value) {
        return;
    }
    resetValidationChecks();
    try {
        activate("Adding...");
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
            currentAction.value = ActionType.ADDED;
            clientList.value = [...clientList.value, newClient];
            timeout({ text: "Client added successfully" });
        } else {
            saveError.value = await extractErrorMessage(response);
            timeout({ text: "Client couldn't be added", isSuccess: false });
        }
    } catch (error) {
        timeout({ text: "Client couldn't be added", isSuccess: false });
    }
}

/**
 * Show the modify modal
 * @param client - The client to modify
 */
function showModifyModal(client: OAuthClient): void {
    saveError.value = null;
    modifyingClient.value = { ...client };
    currentAction.value = ActionType.MODIFY;
    if (modalEl.value) {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call -- RevealModal is untyped
        modalEl.value.open();
    }
}

/**
 * Modify an existing client
 * @param client - The client to modify
 */
async function modifyItem(client: OAuthClient): Promise<void> {
    resetValidationChecks();
    if (isProcessing.value) {
        return;
    }
    if (client.name === "") {
        hasNoName.value = true;
    }
    if (client.redirectUri === "") {
        hasNoRedirect.value = true;
    }
    if (hasNoName.value || hasNoRedirect.value) {
        return;
    }
    resetValidationChecks();
    try {
        if (!client._links?.patch) {
            throw new Error("No patch link found for client");
        }
        activate("Modifying...");
        const response = await fetch(client._links?.patch, {
            method: "PATCH",
            body: JSON.stringify(client),
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
            },
        });
        if (response.ok) {
            timeout({ text: "Client modified successfully" });
            // Replace the existing modified client with the new version
            const modifiedClient = (await response.json()) as OAuthClient;
            clientList.value = clientList.value.map((c) => (c.id === modifiedClient.id ? modifiedClient : c));
            closeModal();
        } else {
            saveError.value = await extractErrorMessage(response);
            timeout({ text: "Client couldn't be modified", isSuccess: false });
        }
    } catch (error) {
        timeout({ text: "Client couldn't be modified", isSuccess: false });
    }
}

/**
 * Show the regenerate modal
 * @param client - The client to regenerate
 */
function showRegenerateModal(client: OAuthClient): void {
    saveError.value = null;
    regeneratingClient.value = { ...client };
    currentAction.value = ActionType.REGENERATE;
    if (modalEl.value) {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call -- RevealModal is untyped
        modalEl.value.open();
    }
}

/**
 * Regenerate the client secret
 * @param client - The client to regenerate
 */
async function regenerateClient(client: OAuthClient): Promise<void> {
    if (isProcessing.value) {
        return;
    }
    try {
        if (!client._links?.regenerate) {
            throw new Error("No patch link found for client");
        }
        activate("Regenerating...");
        const response = await fetch(client._links.regenerate, {
            method: "POST",
            headers: {
                Accept: "application/json",
            },
        });
        if (response.ok) {
            regeneratingClient.value = (await response.json()) as OAuthClient;
            currentAction.value = ActionType.REGENERATED;
            timeout({ text: "Client regenerated successfully" });
        } else {
            saveError.value = await extractErrorMessage(response);
            timeout({ text: "Client couldn't be regenerated", isSuccess: false });
        }
    } catch (error) {
        timeout({ text: "Client couldn't be regenerated", isSuccess: false });
    }
}

/**
 * Show the delete modal
 * @param client - The client to delete
 */
function showDeleteModal(client: OAuthClient): void {
    deletingClient.value = { ...client };
    currentAction.value = ActionType.DELETE;
    if (modalEl.value) {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call -- RevealModal is untyped
        modalEl.value.open();
    }
}

/**
 * Delete a client
 * @param client - The client to delete
 */
async function deleteClient(client: OAuthClient): Promise<void> {
    if (isProcessing.value) {
        return;
    }
    try {
        if (!client._links?.delete) {
            throw new Error("No patch link found for client");
        }
        activate("Deleting...");
        const response = await fetch(client._links.delete, {
            method: "DELETE",
        });
        if (response.ok) {
            clientList.value = clientList.value.filter((c) => c.id !== client.id);
            timeout({ text: "Client deleted successfully" });
            closeModal();
        } else {
            saveError.value = await extractErrorMessage(response);
            timeout({ text: "Client couldn't be deleted", isSuccess: false });
        }
    } catch (error) {
        timeout({ text: "Client couldn't be deleted", isSuccess: false });
    }
}

/**
 * Search callback for the user selectize.
 * This method is debounced so that the search doesn't trigger until
 * the user has stopped typing for `SEARCH_RESULT_RETRIEVE_DELAY` milliseconds.
 * @param {string} query -The search string
 * @param {Function} loading - A function to call when the loading state changes (accepts a Boolean)
 */
const onUserSearch = debounce((query: string, loading: (isLoading: boolean) => void) => {
    if (query.length < 3) {
        return;
    }
    loading(true);
    const searchUrl = new URL(props.userSearchEndpoint, window.location.href);
    searchUrl.search = new URLSearchParams(Object.assign(userSearchAdditionalParams, { search: query })).toString();
    fetch(searchUrl)
        .then((response) => response.json())
        .then((jsonResponse) => {
            searchUsers.value = (jsonResponse as VueSelectData).data as { fullName: string }[];
        })
        .catch(() => {
            timeout({ text: "An unexpected error occurred", isSuccess: false });
        })
        .finally(() => {
            loading(false);
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
    <loading-table v-if="isDataLoading" />
    <data-load-error v-else-if="hasDataLoadError" data-title="client list" />
    <div v-else>
        <div class="small-12 island">
            <template v-if="clientList.length === 0">
                <div id="listEmptyState" class="empty-state">
                    <i class="icon-list"></i>
                    <p>
                        No oAuth clients have been configured.
                        <a href="#add-client" @click.prevent="showAddModal">Add an oAuth client</a>.
                    </p>
                </div>
            </template>
            <template v-else>
                <table class="no-margin">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Redirect URI</th>
                            <th>Client ID</th>
                            <th>Client Credentials User</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
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
                            <td class="actions-small-2">
                                <nav>
                                    <a
                                        href="#modify"
                                        class="icon-modify no-text"
                                        title="Modify this client"
                                        aria-label="Modify this client"
                                        @click.prevent="showModifyModal(row)"
                                    ></a>
                                    <a
                                        href="#regenerate"
                                        class="icon-refresh no-text"
                                        title="Regenerate this client&rsquo;s secret"
                                        aria-label="Regenerate this client&rsquo;s secret"
                                        @click.prevent="showRegenerateModal(row)"
                                    ></a>
                                    <a
                                        href="#delete"
                                        class="icon-delete no-text"
                                        title="Delete this client"
                                        aria-label="Delete this client"
                                        @click.prevent="showDeleteModal(row)"
                                    ></a>
                                </nav>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="component-action">
                    <section>
                        <button @click.prevent="showAddModal">Add oAuth Client</button>
                    </section>
                </div>
            </template>
        </div>
    </div>
    <reveal-modal ref="modalEl" @closed="onModalClosed">
        <template v-if="currentAction === ActionType.ADD">
            <h2>Add oAuth Client</h2>
            <div v-if="saveError" class="alert-box alert">{{ saveError }}</div>
            <div class="l-flex-col l-gap-m">
                <div class="f-no-margins">
                    <label for="addClient-name" class="required">Name</label>
                    <p class="meta">
                        A unique name for this client: this name will be displayed to a Schoolbox user when they are
                        asked whether they wish to share their details.
                    </p>
                    <input id="addClient-name" v-model="addingClient.name" required />
                    <label v-if="hasNoName" class="error">Please enter a Name</label>
                </div>
                <div class="f-no-margins">
                    <label for="addClient-redirectUri" class="required">Redirect URI</label>
                    <p class="meta">
                        When the user approves a request to share details, where should they be redirected to? (This URI
                        must be able to process an oAuth authorization code.)
                    </p>
                    <input id="addClient-redirectUri" v-model="addingClient.redirectUri" required />
                    <label v-if="hasNoRedirect" class="error">Please enter a Redirect URI</label>
                </div>
                <div class="f-no-margins">
                    <label for="addClient-backChannelLogoutUri">Back-channel Logout URI</label>
                    <p class="meta">When the user logs out of Schoolbox: send a logout request to this URI.</p>
                    <input id="modifyItem-addClient" v-model="addingClient.backChannelLogoutUri" />
                </div>
                <div class="f-no-margins">
                    <label for="addClient-clientCredentialsUser">Client Credentials User</label>
                    <p class="meta">
                        Any client credentials requests (i.e. requests made by the client backend to this Schoolbox
                        instance, without interaction by an actual user) will be authenticated as this user; if no user
                        is set here, this client may not use client credentials requests.
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
                            <span class="d-center">{{ (option as VueSelectOption).fullName }}</span>
                        </template>
                        <template #selected-option="option">
                            <span class="selected d-center">{{ (option as VueSelectOption).fullName }}</span>
                        </template>
                    </v-select>
                </div>
            </div>
            <div class="component-action">
                <section>
                    <button @click.prevent="addClient">Add</button>
                    <button @click.prevent="closeModal">Cancel</button>
                </section>
            </div>
        </template>
        <template v-else-if="currentAction === ActionType.ADDED">
            <h2>oAuth Client Added</h2>
            <div class="alert-box warning">
                You must copy and store the client secret: it will no longer be retrievable after closing this dialog.
            </div>
            <label for="itemAdded-name">Name</label>
            <input id="itemAdded-name" v-model="addingClient.name" readonly />
            <label for="itemAdded-redirectUri">Redirect URI</label>
            <input id="itemAdded-redirectUri" v-model="addingClient.redirectUri" readonly />
            <label for="itemAdded-backChannelLogoutUri">Back-channel Logout URI</label>
            <input id="itemAdded-backChannelLogoutUri" v-model="addingClient.backChannelLogoutUri" readonly />
            <label for="itemAdded-clientId">Client ID</label>
            <input id="itemAdded-clientId" v-model="addingClient.clientId" readonly />
            <label for="itemAdded-clientSecret" class="required">Client Secret</label>
            <input id="itemAdded-clientSecret" v-model="addingClient.clientSecret" readonly />
            <label for="itemAdded-clientCredentialsUser">Client Credentials User</label>
            <select id="itemAdded-clientCredentialsUser" readonly>
                <option v-if="addingClient.clientCredentialsUser">
                    {{ addingClient.clientCredentialsUser.fullName }}
                </option>
                <option v-else>No user</option>
            </select>
            <div class="component-action">
                <section>
                    <button @click.prevent="closeModal">Close</button>
                </section>
            </div>
        </template>
        <template v-else-if="currentAction === ActionType.MODIFY">
            <h2>Modify oAuth Client</h2>
            <div v-if="saveError" class="alert-box alert">{{ saveError }}</div>
            <div class="l-flex-col l-gap-m">
                <div class="f-no-margins">
                    <label for="modifyItem-name" class="required">Name</label>
                    <p class="meta">
                        A unique name for this client: this name will be displayed to a Schoolbox user when they are
                        asked whether they wish to share their details.
                    </p>
                    <input id="modifyItem-name" v-model="modifyingClient.name" required />
                    <label v-if="hasNoName" class="error">Please enter a Name</label>
                </div>
                <div class="f-no-margins">
                    <label for="modifyItem-redirectUri" class="required">Redirect URI</label>
                    <p class="meta">
                        When the user approves a request to share details, where should they be redirected to? (This URI
                        must be able to process an oAuth authorization code.)
                    </p>
                    <input id="modifyItem-redirectUri" v-model="modifyingClient.redirectUri" required />
                    <label v-if="hasNoRedirect" class="error">Please enter a Redirect URI</label>
                </div>
                <div class="f-no-margins">
                    <label for="modifyItem-backChannelLogoutUri">Back-channel Logout URI</label>
                    <p class="meta">When the user logs out of Schoolbox: send a logout request to this URI.</p>
                    <input id="modifyItem-backChannelLogoutUri" v-model="modifyingClient.backChannelLogoutUri" />
                </div>
                <div class="f-no-margins">
                    <label for="modifyItem-clientCredentialsUser">Client Credentials User</label>
                    <p class="meta">
                        Any client credentials requests (i.e. requests made by the client backend to this Schoolbox
                        instance, without interaction by an actual user) will be authenticated as this user; if no user
                        is set here, this client may not use client credentials requests.
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
                            <span class="d-center">{{ (option as VueSelectOption).fullName }}</span>
                        </template>
                        <template #selected-option="option">
                            <span class="selected d-center">{{ (option as VueSelectOption).fullName }}</span>
                        </template>
                    </v-select>
                </div>
            </div>
            <div class="component-action">
                <section>
                    <button @click.prevent="modifyItem(modifyingClient)">Modify</button>
                    <button @click.prevent="closeModal">Cancel</button>
                </section>
            </div>
        </template>
        <template v-else-if="currentAction === ActionType.REGENERATE">
            <h2>Regenerate Client Secret for oAuth Client {{ regeneratingClient.name }}?</h2>
            <div v-if="saveError" class="alert-box alert">{{ saveError }}</div>
            <div class="alert-box warning">
                Regenerating this client&rsquo;s secret will invalidate the configuration of this Schoolbox instance as
                an oAuth Provider within the client application: it will need to be updated with the regenerated secret.
            </div>
            <div class="component-action">
                <section>
                    <button @click.prevent="regenerateClient(regeneratingClient)">Regenerate</button>
                    <button @click.prevent="closeModal">Cancel</button>
                </section>
            </div>
        </template>
        <template v-else-if="currentAction === ActionType.REGENERATED">
            <h2>Client Secret for oAuth Client {{ regeneratingClient.name }} Regenerated</h2>
            <div class="alert-box warning">
                You must copy and store the client secret: it will no longer be retrievable after closing this dialog.
            </div>
            <label for="itemRegenerated-clientSecret" class="required">Client Secret</label>
            <input id="itemRegenerated-clientSecret" v-model="regeneratingClient.clientSecret" readonly />
            <div class="component-action">
                <section>
                    <button @click.prevent="closeModal">Close</button>
                </section>
            </div>
        </template>
        <template v-else-if="currentAction === ActionType.DELETE">
            <h2>Delete oAuth Client {{ deletingClient.name }}?</h2>
            <div class="component-action">
                <section>
                    <button @click.prevent="deleteClient(deletingClient)">Delete</button>
                    <button @click.prevent="closeModal">Cancel</button>
                </section>
            </div>
        </template>
    </reveal-modal>
</template>
