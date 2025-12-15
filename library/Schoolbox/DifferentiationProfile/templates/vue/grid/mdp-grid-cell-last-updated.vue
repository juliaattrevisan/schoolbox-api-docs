<script setup lang="ts">
/**
 * Display the last updated date and current status of a differentiation profile
 */

import { ProfileStatus, type Profile } from "../../js/types/manageDifferentiationProfilesTypes";

const props = defineProps<{
    profile: Profile;
}>();
</script>

<template>
    <div class="l-flex-col l-pad-s l-gap-m f-no-margins">
        <p>{{ props.profile.updated_at }}</p>
        <sbx-label
            v-if="props.profile.status?.includes(ProfileStatus.OUT_OF_DATE)"
            text="Out of date"
            sentiment="warning"
        ></sbx-label>
        <sbx-label
            v-if="props.profile.status?.includes(ProfileStatus.EXPIRED)"
            text="Expired"
            sentiment="alert"
        ></sbx-label>
        <sbx-label
            v-if="props.profile.status?.includes(ProfileStatus.UNPOPULATED)"
            text="Unpopulated"
            sentiment="neutral"
        ></sbx-label>
    </div>
</template>

<style scoped></style>
