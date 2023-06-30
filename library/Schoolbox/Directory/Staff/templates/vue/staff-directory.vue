<script setup lang="ts">
/**
 * To display the staff directory in a sortable, searchable table
 */

import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";

const props = defineProps({
    /**
     * the endpoint to retrieve all the staff data from
     */
    payload: {
        type: String,
        required: true,
        default: "",
    },
});

const rows = window.Vue.ref([] as any[]); // the staff data to display
const isLoading = window.Vue.ref(true); // whether the staff data has been loaded
const hasLoadError = window.Vue.ref(false); // whether there was an error loading the staff data

/**
 * load the staff data for display
 */
async function loadRows() {
    try {
        const response = await fetch(props.payload);
        rows.value = await response.json();
    } catch (error) {
        hasLoadError.value = true; // display error note in the DOM
    } finally {
        isLoading.value = false; // hide the loading table
    }
}

/**
 * Start loading the group data
 */
window.Vue.onMounted(() => {
    loadRows();
});

/**
 * Reload the page to try loading again
 */
function onRefreshSelected() {
    window.location.reload();
}
</script>

<template>
    <div class="row">
        <div class="small-12 columns">
            <loading-table v-if="isLoading" />
            <section v-else-if="hasLoadError" class="content">
                <div class="alert-box alert">
                    Sorry, we are currently unable to load the Staff data. Please
                    <a href="#refresh-page" title="refresh the page" @click.prevent="onRefreshSelected"
                        >refresh the page</a
                    >
                    to try again and contact your support if the problem persists.
                </div>
            </section>
            <div v-else>
                <p>Loaded</p>
            </div>
        </div>
    </div>
</template>

<style scoped></style>
