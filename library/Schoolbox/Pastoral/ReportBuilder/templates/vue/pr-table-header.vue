<script setup lang="ts">
/**
 * Table header component for pastoral reports.
 * Renders headers with optional sorting functionality.
 */

import type { TableHeader, Config } from "../js/types/pastoralReportTypes";

const props = defineProps<{
    headers: TableHeader[];
    config: Config;
    recordsAndPoints: string[];
    sortBy: string;
    sortOrder: "asc" | "desc";
}>();

const recordsOrPoints = defineModel<string>("records-or-points", { default: "Records" });

// const emit = defineEmits<{
//     sort: [key: string];
// }>();

/**
 * Handles the sort action when a sortable header is clicked
 * @param header - the header object that was clicked
 */
// function onSortSelected(header: TableHeader): void {
//     if (header.isSortable) {
//         emit("sort", header.key);
//     }
// }

/**
 * If we have sub titles, it means the titles need to be displayed on the same row as the records/points radio buttons,
 * otherwise the radio buttons just display on their own row
 */
const hasSubTitles = props.headers.some((header) => header.subTitle && header.subTitle.length > 0);

/**
 * Creates an array representing what should appear in the first header row after the radio buttons
 * @returns an array of objects representing what should appear in the first header row
 */
function getFirstRowHeaders(): Array<{ title: string; colspan: number; isEmpty: boolean }> {
    const result: Array<{ title: string; colspan: number; isEmpty: boolean }> = [];

    // Skip the first 2 headers (Name and Total) as they're always covered by radio buttons
    let i = 2;

    while (i < props.headers.length) {
        const header = props.headers[i];

        if (header.subTitle && header.subTitle.length > 0) {
            // Count consecutive headers with the same title that have subtitles
            let count = 1;
            while (
                i + count < props.headers.length &&
                props.headers[i + count].title === header.title &&
                props.headers[i + count].subTitle &&
                props.headers[i + count].subTitle!.length > 0
            ) {
                count += 1;
            }

            result.push({
                title: header.title,
                colspan: count,
                isEmpty: false,
            });

            i += count;
        } else {
            // Header without subtitle - add empty cell
            result.push({
                title: "",
                colspan: 1,
                isEmpty: true,
            });
            i += 1;
        }
    }

    return result;
}
</script>

<template>
    <thead class="pr-table-header">
        <tr>
            <th :colspan="hasSubTitles ? 1 : props.headers.length" class="u-border-right u-sticky-left">
                <div class="checklist">
                    <ul class="option-list l-flex l-gap-m f-no-margins">
                        <li v-for="(option, index) in props.recordsAndPoints" :key="`option-${index}`">
                            <input :id="option" v-model="recordsOrPoints" type="radio" :value="option" />
                            <label :for="option" class="f-no-margins">{{ option }}</label>
                        </li>
                    </ul>
                </div>
            </th>
            <td class="u-border-right">&nbsp;</td>
            <template v-if="hasSubTitles">
                <th
                    v-for="(headerCell, index) in getFirstRowHeaders()"
                    :key="`first-row-${index}`"
                    :colspan="headerCell.colspan"
                    class="u-border-right"
                >
                    {{ headerCell.isEmpty ? "" : headerCell.title }}
                </th>
            </template>
        </tr>
        <tr>
            <th
                v-for="header in props.headers"
                :key="header.key"
                :class="`u-border-right title-cell ${header.key === 'user' ? 'u-sticky-left user-cell' : ''} ${header.key === 'total' ? 'total-cell' : ''}`"
            >
                <!-- active sort class - ${props.sortBy === header.key ? 'sorted' : ''} -->
                <!-- <button class="title-button f-always-show" @click="onSortSelected(header)">
                    <span>{{ header.subTitle ? header.subTitle : header.title }}</span>
                    <span v-if="header.isSortable && props.sortBy === header.key" class="sort-indicator">
                        {{ props.sortOrder === "asc" ? "↑" : "↓" }}
                    </span>
                    <span v-else-if="header.isSortable" class="sort-indicator sort-indicator--inactive"> ↕ </span>
                </button> -->
                {{ header.subTitle ? header.subTitle : header.title }}
            </th>
        </tr>
    </thead>
</template>

<style scoped lang="scss">
.title-cell {
    min-width: 100px;
    // position: relative;
    &:has(.title-button) {
        padding: 0; // padding is handled by the button instead
    }
    &.sorted {
        background-color: var(--accent-background-light, #f0f0f0);
    }
}

.user-cell {
    min-width: 250px;
}

.total-cell {
    background: lightgray;
}

.title-button {
    background: none;
    display: flex; // to align the text to the top left
    justify-content: space-between;
    align-items: center;
    height: 100%;
    margin: 0;
    padding: 0.625rem;
    text-align: left;
    width: 100%;
    &:hover {
        background: var(--accent-background);
    }
}

.sort-indicator {
    margin-left: 0.5rem;
    font-weight: bold;
    &--inactive {
        opacity: 0.3;
    }
}
</style>
