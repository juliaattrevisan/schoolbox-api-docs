<script setup lang="ts">
/**
 * To be able to customise what's displayed on a user profile page
 */

import PeFields from "./pe-fields.vue";
import type { ProfileEditorRoleType } from "./types/profileEditorTypes";
import type { ProfileEditorPayload } from "./types/profilePayloadTypes";

interface Props {
    endpoint: string;
}

const props = defineProps<Props>();

const data = window.Vue.ref<ProfileEditorPayload["data"] | null>(null);
const isLoading = window.Vue.ref(true);

const selectedRoleType = window.Vue.ref<ProfileEditorRoleType | null>(null);

/**
 * Grab the initial data for all role types from the provided endpoint
 */
async function fetchData(): Promise<void> {
    const response = await fetch(props.endpoint);
    const responseJson = (await response.json()) as unknown as ProfileEditorPayload;
    data.value = responseJson.data;
    isLoading.value = false;
}

void fetchData();
</script>

<template>
    <p v-if="isLoading">Loading...</p>
    <p v-else-if="data === null">Failed to load data</p>
    <template v-else>
        <a v-for="role in data.roleTypes" :key="role.name" @click="selectedRoleType = role"> {{ role.name }} </a>
    </template>

    <template v-if="selectedRoleType !== null">
        <pe-fields :fields="selectedRoleType.fields" />
    </template>
</template>

<style scoped></style>
