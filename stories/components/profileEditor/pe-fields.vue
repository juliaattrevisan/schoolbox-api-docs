<script setup lang="ts">
/**
 * To customise the fields displayed on a user profile page
 */

import { useProfileEditorStore } from "./store/profileEditorStore";

const peStore = useProfileEditorStore();
</script>

<template>
    <div v-if="peStore.selectedRoleType">
        <p v-for="field in peStore.selectedRoleType.fields.available" :key="field.field">{{ field.displayAs }}</p>
    </div>
</template>

<style scoped></style>
