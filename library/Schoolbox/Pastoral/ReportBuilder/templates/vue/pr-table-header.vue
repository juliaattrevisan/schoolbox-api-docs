<script setup lang="ts">
/**
 * Table header component for pastoral reports.
 * Renders headers with optional sorting functionality.
 */

import type { PastoralReportTableHeader } from "../js/types/pastoralReportTypes";

type Props = {
    headers: PastoralReportTableHeader[];
};
const props = defineProps<Props>();

const emit = defineEmits<{
    sort: [key: string];
}>();

/**
 * Handles the sort action when a sortable header is clicked
 * @param header - the header object that was clicked
 */
function handleSort(header: PastoralReportTableHeader): void {
    if (header.isSortable) {
        emit("sort", header.key);
    }
}
</script>

<template>
    <thead class="pr-table-header">
        <tr>
            <th
                v-for="header in props.headers"
                :key="header.key"
                :class="{
                    'pr-table-header__cell': true,
                    'pr-table-header__cell--sortable': header.isSortable,
                }"
                @click="handleSort(header)"
            >
                <div class="pr-table-header__content">
                    <div class="pr-table-header__title">{{ header.title }}</div>
                    <div v-if="header.subTitle" class="pr-table-header__subtitle">
                        {{ header.subTitle }}
                    </div>
                </div>
                <div v-if="header.isSortable" class="pr-table-header__sort-icon">↕</div>
            </th>
        </tr>
    </thead>
</template>

<style scoped lang="scss"></style>
