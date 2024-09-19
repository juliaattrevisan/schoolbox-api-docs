<script setup lang="ts">
/**
 * To customise the fields displayed on a user profile page
 */

import type { ProfileEditorRoleType } from "./types/profileEditorTypes";

interface Props {
    fields: ProfileEditorRoleType["fields"];
}

const props = defineProps<Props>();
</script>

<template>
    <div>
        <p v-for="field in props.fields.available" :key="field.field">{{ field.displayAs }}</p>
    </div>
</template>

<style scoped></style>
