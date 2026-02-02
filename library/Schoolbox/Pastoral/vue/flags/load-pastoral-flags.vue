<script setup lang="ts">
/**
 * Load in pastoral flags for a group of students and display in specified container elements
 */

import { apiRequest } from "@js/util/sbx/apiUtils";
import type { PastoralFlag } from "@schoolboxLibrary/Pastoral/types/PastoralFlag";
import { isAbortError, hasErrorMessage } from "@js/util/errorUtils";

const props = defineProps<{
    url: string;
}>();

// const payload = window.Vue.ref<RecordsPayload | null>(null); // to store the payload that matches the current query
const loadError = window.Vue.ref<string | null>(null); // to store any error that occurs during the load

/**
 * Load the pastoral flags from the provided URL
 */
async function loadFlags(): Promise<void> {
    try {
        const response = await apiRequest<Record<string, PastoralFlag[]>>({
            url: props.url,
            method: "GET",
        });
        if (!response.success) {
            throw new Error(response.message || "Failed to load data");
        }
        // const newPayload = response.data;
        // check if the response does not have the expected structure
        // if (!Array.isArray(newPayload.data.results)) {
        //     throw new Error("Unexpected response format");
        // }
        // reset the payload if it's a new load or append to the existing results if it's a load of more results
        // if (isNewLoad.value) {
        //     payload.value = newPayload;
        // } else {
        //     payload.value?.data.results.push(...newPayload.data.results);
        //     payload.value!.metadata = newPayload.metadata; // Update metadata to reflect the latest state
        // }
    } catch (error) {
        // ignore abort errors, as the previous request was cancelled
        if (isAbortError(error)) {
            return;
        }
        if (error instanceof Error && hasErrorMessage(error)) {
            loadError.value = error.message;
            return;
        }
        loadError.value = "An unexpected error occurred while loading the data.";
        console.error(error);
    }
}

/**
 * Load the flags when the component is mounted
 */
window.Vue.onMounted(() => {
    void loadFlags();
});
</script>

<template>
    <div>{{ props.url }}</div>
</template>

<style scoped></style>
