<script setup lang="ts">
/**
 * To be able to customise what's displayed on a user profile page
 */

import PeFields from "./pe-fields.vue";

interface Props {
    endpoint: string;
}

const props = defineProps<Props>();

const data = window.Vue.ref({});
const isLoaded = window.Vue.ref(false);

async function fetchData(): void {
    const response = await fetch(props.endpoint);
    const responseJson = await response.json();
    data.value = responseJson.data.fields;
    isLoaded.value = true;
}

void fetchData();
</script>

<template>
    <div v-if="isLoaded">
        <pe-fields :fields="data" />
    </div>
</template>

<style scoped></style>
