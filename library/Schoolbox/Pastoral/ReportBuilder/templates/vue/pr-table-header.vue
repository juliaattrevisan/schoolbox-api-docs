<script setup lang="ts">
/**
 * Table header component for pastoral reports.
 * Renders headers with optional sorting functionality.
 */

import type { TableHeader } from "../js/types/pastoralReportTypes";

type Props = {
    headers: TableHeader[];
    recordsAndPoints: string[];
};
const props = defineProps<Props>();

const recordsOrPoints = defineModel<string>("records-or-points", { default: "Records" });

const emit = defineEmits<{
    sort: [key: string];
}>();

/**
 * Handles the sort action when a sortable header is clicked
 * @param header - the header object that was clicked
 */
function handleSort(header: TableHeader): void {
    if (header.isSortable) {
        emit("sort", header.key);
    }
}

/**
 * If we have sub titles, it means the titles need to be displayed on the same row as the records/points radio buttons,
 * otherwise the radio buttons just display on their own row
 */
const hasSubTitles = props.headers.some((header) => header.subTitle && header.subTitle.length > 0);

/**
 * Groups headers by title to calculate colspan for duplicate titles
 * @returns an array of grouped header objects with title, colspan, and original headers
 */
function getGroupedHeaders(): Array<{ title: string; colspan: number; headers: TableHeader[] }> {
    const groups: { [key: string]: TableHeader[] } = {};
    // Group headers by title
    props.headers.forEach((header) => {
        // if the header has a subTitle, it should be grouped
        if (header.subTitle && header.subTitle.length > 0) {
            // Initialize group if it doesn't exist
            if (!groups[header.title]) {
                groups[header.title] = [];
            }
            // Add header to the group
            groups[header.title].push(header);
        }
    });
    // Convert to array format with colspan
    return Object.entries(groups).map(([title, headers]) => ({
        title,
        colspan: headers.length,
        headers,
    }));
}
</script>

<template>
    <thead class="pr-table-header">
        <tr>
            <th :colspan="hasSubTitles ? 2 : props.headers.length" class="u-border-right">
                <div class="checklist">
                    <ul class="option-list l-flex l-gap-m f-no-margins">
                        <li v-for="(option, index) in props.recordsAndPoints" :key="`option-${index}`">
                            <input :id="option" v-model="recordsOrPoints" type="radio" :value="option" />
                            <label :for="option" class="f-no-margins">{{ option }}</label>
                        </li>
                    </ul>
                </div>
            </th>
            <template v-if="hasSubTitles">
                <th
                    v-for="group in getGroupedHeaders()"
                    :key="`${group.title}-title`"
                    :colspan="group.colspan"
                    class="u-border-right"
                >
                    {{ group.title }}
                </th>
            </template>
        </tr>
        <tr>
            <th
                v-for="header in props.headers"
                :key="header.key"
                :class="`u-border-right ${header.key === 'total' ? 'total-cell' : ''}`"
                @click="handleSort(header)"
            >
                {{ header.subTitle ? header.subTitle : header.title }}
            </th>
        </tr>
    </thead>
</template>

<style scoped lang="scss">
.total-cell {
    background: lightgray;
}
</style>
