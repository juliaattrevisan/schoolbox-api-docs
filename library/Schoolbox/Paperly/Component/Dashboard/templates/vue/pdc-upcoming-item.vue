<script setup lang="ts">
/**
 * To display a single upcoming item within the upcoming tasks list
 */

import type { UpcomingItem } from "../js/types/UpcomingItem";

interface Props {
    item: UpcomingItem; // The upcoming item to display
}
const props = defineProps<Props>();

/**
 * To display the formatted due date
 * @returns - the formatted due date eg: Mon 01 Jan at 12:00pm
 */
function getDate(): string {
    return window.dayjs(props.item.due).format("ddd DD MMM [at] h:mma");
}
</script>

<template>
    <div class="item l-flex-col l-gap-xs l-pad-m--horiz l-pad-s--vert">
        <a :href="props.item.url" :title="`View ${props.item.title}`">{{ props.item.title }}</a>
        <p v-if="props.item.due || props.item.status" class="meta f-no-margins">
            <template v-if="props.item.status && props.item.due">{{ props.item.status }} - Due date: {{ getDate() }}</template>
            <template v-else-if="props.item.due && !props.item.status">Due date: {{ getDate() }}</template>
            <template v-else>{{ props.item.status }}</template>
        </p>
    </div>
</template>

<style scoped>
.item:not(:last-child) {
    border-bottom: 1px solid var(--body-background);
}
</style>
