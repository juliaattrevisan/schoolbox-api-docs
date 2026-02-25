<script setup lang="ts">
/**
 * To display a grid-like display
 */

import type { Header } from "./types/lazyTableTypes";

const props = defineProps<{
    header: Header[];
    rows: Record<string, unknown>[];
    isLoading: boolean;
    sortBy: string;
    order: "ASC" | "DESC";
}>();

const emit = defineEmits<{
    sort: [key: string];
}>();

/**
 * Handles the sort action when a sortable header is clicked
 * @param key - the key of the header that was clicked
 */
function onSortSelected(key: string): void {
    emit("sort", key);
}
</script>

<template>
    <table class="lazy-table">
        <thead>
            <tr class="lazy-table__row lazy-table__row--header">
                <th
                    v-for="item in props.header"
                    :key="item.id"
                    class="u-sticky-top"
                    :class="{
                        'u-sticky-left': item.isSticky,
                    }"
                    :style="{ width: `${item.width}px`, minWidth: `${item.width}px` }"
                >
                    <button
                        v-if="item.isSortable"
                        class="title-button f-always-show"
                        @click.prevent="onSortSelected(item.key)"
                    >
                        <span>
                            {{ item.title }}
                        </span>
                        <sbx-icon
                            v-if="props.sortBy === item.key"
                            :name="`${props.order === 'ASC' ? 'sort-up' : 'sort-down'}`"
                            size="0.825rem"
                        />
                        <sbx-icon v-else name="sortable" size="0.825rem" />
                    </button>
                    <template v-else>{{ item.title }}</template>
                </th>
            </tr>
        </thead>

        <tbody v-if="!props.isLoading">
            <tr
                v-for="(row, rowIndex) in props.rows"
                :key="rowIndex"
                class="lazy-table__row"
                :data-test="`lazy-grid-row-${rowIndex}`"
            >
                <td
                    v-for="(item, cellIndex) in props.header"
                    :key="item.id"
                    :class="{ 'u-sticky-left': item.isSticky }"
                    :data-test="`lazy-grid-row-cell-${rowIndex}-${cellIndex}-${item.key}`"
                >
                    <slot :name="item.type" :row="row" :header="item" :row-index="rowIndex">
                        {{ row[item.key] }}
                    </slot>
                </td>
            </tr>
        </tbody>
    </table>
</template>

<style scoped lang="scss">
.lazy-table {
    border-collapse: separate;
    border-spacing: 0;
    position: relative;
    width: max-content; /* so sticky left elements stick for the entire width */
}

.lazy-table__row > th,
.lazy-table__row > td {
    background-color: #fff;
    border-color: var(--body-background);
    border-style: solid;
    border-width: 1px 0 1px 0; // Unless overridden below
    font-size: 0.8125rem;
    overflow-wrap: anywhere;
    text-wrap: wrap;
    &:not(:last-child) {
        border-right-width: 1px;
    }
    &:not(:first-child) {
        border-left-width: 1px;
    }
}
.lazy-table__row--header > th {
    border-top-width: 0px;
    color: #000;
    font-weight: 600;
    padding: 0.625rem;
    white-space: nowrap;
}

.u-sticky-top.u-sticky-left {
    z-index: calc(var(--sbx-depth-ui-sticky) + 3); // On top of both the left column, the header, and the dropdowns
}

.title-button {
    align-items: center;
    background: none;
    display: flex;
    gap: var(--spacing-m);
    justify-content: space-between;
    height: auto;
    margin: 0;
    min-height: auto;
    padding: 0;
    text-align: left;
    width: 100%;
    &:hover {
        background: none;
    }
}
</style>
