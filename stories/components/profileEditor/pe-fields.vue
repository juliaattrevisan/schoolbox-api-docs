<script setup lang="ts">
/**
 * To customise the fields displayed on a user profile page
 */

import PeFieldsSelectedItem from "./pe-fields-selected-item.vue";
import { useProfileEditorStore } from "./store/profileEditorStore";

const peStore = useProfileEditorStore();
</script>

<template>
    <div v-if="peStore.selectedRoleType">
        <pe-fields-selected-item
            v-for="field in peStore.selectedRoleType.fields.selected"
            :key="field.field"
            :field="field"
        />
    </div>
</template>

<style scoped></style>
