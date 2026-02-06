<script setup lang="ts">
/**
 * Display the last updated date and current status of a differentiation profile
 */

import { ProfileStatus, type Profile } from "../../../js/types/manageDifferentiationProfilesTypes";

const props = defineProps<{
    profile: Profile;
}>();

/**
 * To format a date string into a short date format
 * @param dateString - the date string to format
 * @returns - the formatted date string or null if the input is null
 */
function formatDateShort(dateString: string | null): string | null {
    if (!dateString) {
        return null;
    }
    return window.dayjs(dateString).format(window.dateDisplayFormats.dayjsDateShort);
}
</script>

<template>
    <div class="l-flex-col l-pad-s l-gap-s f-no-margins">
        <p v-if="props.profile.updated_at">{{ formatDateShort(props.profile.updated_at) }}</p>
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
