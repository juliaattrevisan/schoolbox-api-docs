<script setup lang="ts">
/**
 * To display the staff directory in a sortable, searchable table
 */

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
const hasLoaded = window.Vue.ref(false); // whether the staff data has been loaded
const hasLoadError = window.Vue.ref(false); // whether there was an error loading the staff data

/**
 * load the staff data for display
 */
async function loadRows() {
    try {
        const response = await fetch(props.payload);
        rows.value = await response.json();
        debugger;
        hasLoaded.value = true;
    } catch (error) {
        hasLoadError.value = true; // display error note in the DOM
    }
}

/**
 * Start loading the group data
 */
window.Vue.onMounted(() => {
    loadRows();
});
</script>

<template>
    <div class="row">
        <div class="small-12 columns">
            <p>Hai</p>
        </div>
    </div>
</template>

<style scoped></style>
