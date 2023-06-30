<script setup lang="ts">
/**
 * To display the staff directory in a sortable, searchable table
 */

import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import StaffDirectoryTable from "./staff-directory-table.vue";

interface Props {
    payload: string; // the endpoint to retrieve all the staff data from
}

const props = withDefaults(defineProps<Props>(), {
    payload: "",
});

const data = window.Vue.ref([] as any[]); // the staff data to display
const isLoading = window.Vue.ref(true); // whether the staff data has been loaded
const hasLoadError = window.Vue.ref(false); // whether there was an error loading the staff data

/**
 * load the staff data for display
 */
async function loadData() {
    try {
        const response = await fetch(props.payload);
        data.value = await response.json();
    } catch (error) {
        hasLoadError.value = true; // display load error note
    } finally {
        isLoading.value = false; // hide the loading table
    }
}

/**
 * Start loading the group data
 */
window.Vue.onMounted(() => {
    loadData();
});
</script>

<template>
    <div class="row">
        <div class="small-12 columns">
            <loading-table v-if="isLoading" />
            <data-load-error v-else-if="hasLoadError" data-title="Staff Directory" />
            <staff-directory-table v-else :data="data" />
        </div>
    </div>
</template>

<style scoped></style>
