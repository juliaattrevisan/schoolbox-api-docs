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

/**
 * If we have sub titles, it means the titles need to be displayed on the same row as the records/points radio buttons,
 * otherwise the radio buttons just display on their own row
 */
const hasSubTitles = props.headers.some((header) => header.subTitle && header.subTitle.length > 0);

/**
 * Groups headers by title to calculate colspan for duplicate titles
 * @returns an array of grouped header objects with title, colspan, and original headers
 */
function getGroupedHeaders(): Array<{ title: string; colspan: number; headers: PastoralReportTableHeader[] }> {
    const groups: { [key: string]: PastoralReportTableHeader[] } = {};
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
            <th :colspan="hasSubTitles ? 2 : props.headers.length">
                <div class="checklist">
                    <ul class="option-list">
                        <li>
                            <input id="records" type="radio" value="records" />
                            <label for="records">Records</label>
                        </li>
                        <li>
                            <input id="points" type="radio" value="points" />
                            <label for="points">Points</label>
                        </li>
                    </ul>
                </div>
            </th>
            <template v-if="hasSubTitles">
                <th v-for="group in getGroupedHeaders()" :key="`${group.title}-title`" :colspan="group.colspan">
                    {{ group.title }}
                </th>
            </template>
        </tr>
        <tr>
            <th v-for="header in props.headers" :key="header.key" @click="handleSort(header)">
                {{ header.subTitle ? header.subTitle : header.title }}
            </th>
        </tr>
    </thead>
</template>

<style scoped lang="scss">
th {
    border: 1px solid red;
}
</style>
