<script setup lang="ts">
/**
 * Display the last updated date and current status of a differentiation profile
 */

import type { Profile } from "../../js/types/manageDifferentiationProfilesTypes";

const props = defineProps<{
    profile: Profile;
}>();
</script>

<template>
    <div class="l-flex-col l-pad-s l-gap-m">
        <p>{{ props.profile.updated_at }}</p>
    </div>
</template>

<style scoped></style>
