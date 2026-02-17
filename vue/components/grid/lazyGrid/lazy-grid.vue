<script setup lang="ts">
/**
 * To display a grid-like display
 */

import type { Header } from "./types/lazyGridTypes";

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
 * Generate the grid template columns based on the header widths
 */
const gridTemplateColumns = window.Vue.computed(() => {
    return props.header.map((item) => `${item.width}px`).join(" ");
});

/**
 * Handles the sort action when a sortable header is clicked
 * @param key - the key of the header that was clicked
 */
function onSortSelected(key: string): void {
    emit("sort", key);
}
</script>

<template>
    <div class="lazy-grid">
        <div ref="headerRowEl" class="lazy-grid__row lazy-grid__row--header">
            <div
                v-for="item in props.header"
                :key="item.id"
                class="u-sticky-top"
                :class="{
                    'u-sticky-left': item.isSticky,
                }"
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
            </div>
        </div>
        <template v-if="!props.isLoading">
            <div
                v-for="(row, rowIndex) in props.rows"
                :key="rowIndex"
                class="lazy-grid__row"
                :data-test="`lazy-grid-row-${rowIndex}`"
            >
                <div
                    v-for="(item, cellIndex) in props.header"
                    :key="item.id"
                    :class="{
                        'u-sticky-left': item.isSticky,
                    }"
                    :data-test="`lazy-grid-row-cell-${rowIndex}-${cellIndex}-${item.key}`"
                >
                    <!-- Use dynamic slot based on header type, fallback to default content -->
                    <slot :name="item.type" :row="row" :header="item" :row-index="rowIndex">
                        <!-- Default content if no slot is provided -->
                        {{ row[item.key] }}
                    </slot>
                </div>
            </div>
        </template>
    </div>
</template>

<style scoped lang="scss">
.lazy-grid {
    display: grid;
    grid-template-columns: v-bind(gridTemplateColumns);
    position: relative;
    width: max-content; /* so sticky left elements stick for the entire width */
}

.lazy-grid__row {
    display: contents; // Allow the children to be displayed as a grid
    > div {
        background-color: #fff;
        border-color: var(--body-background);
        border-style: solid;
        border-width: 1px 0 1px 0; // Unless overridden below
        overflow-wrap: anywhere;
        text-wrap: wrap;
        &:not(:last-child) {
            border-right-width: 1px;
        }
        &:not(:first-child) {
            border-left-width: 1px;
        }
    }
    &--header > div {
        border-top-width: 0px;
        color: #000;
        font-weight: 600;
        padding: 0.625rem;
        white-space: nowrap;
    }
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
