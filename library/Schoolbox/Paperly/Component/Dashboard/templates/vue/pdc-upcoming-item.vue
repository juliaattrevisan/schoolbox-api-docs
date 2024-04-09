<script setup lang="ts">
/**
 * To display a single upcoming item within the upcoming tasks list
 */

import type { UpcomingItem } from "../js/types/UpcomingItem";

interface Props {
    item: UpcomingItem; // The folder id to display the nodes for
}
const props = defineProps<Props>();

/**
 * To dispaly the formatted due date
 * @returns {string} - the formatted due date eg: Mon 01 Jan at 12:00pm
 */
function getDate() {
    return window.dayjs(props.item.due).format("ddd DD MMM [at] h:mma");
}
</script>

<template>
    <div class="l-flex-col l-gap-xs l-pad-s">
        <a :href="props.item.url" :title="`View ${props.item.title}`">{{ props.item.title }}</a>
        <p class="meta f-no-margins">
            <template v-if="props.item.status">{{ props.item.status }} - Due date: {{ getDate() }}</template>
            <template v-else>Due date: {{ getDate() }}</template>
        </p>
    </div>
</template>

<style scoped></style>
