<script setup lang="ts">
/**
 * To display a grid-like display
 */

import type {
    Header,
    Profile,
} from "../../../../library/Schoolbox/DifferentiationProfile/templates/js/types/manageDifferentiationProfilesTypes";

const props = defineProps<{
    header: Header[];
    rows: { [key: string]: unknown }[];
    isLoading: boolean;
}>();

/**
 * Generate the grid template columns based on the header widths
 */
const gridTemplateColumns = window.Vue.computed(() => {
    return props.header.map((item) => `${item.width}px`).join(" ");
});
</script>

<template>
    <div class="mdp-grid">
        <div ref="headerRowEl" class="mdp-grid__row mdp-grid__row--header">
            <div
                v-for="item in props.header"
                :key="item.id"
                class="fixed-top"
                :class="{
                    'fixed-left': item.isFixed,
                }"
            >
                {{ item.title }}
            </div>
        </div>
        <template v-if="!props.isLoading">
            <div
                v-for="(row, index) in props.rows"
                :key="index"
                class="mdp-grid__row"
                :data-test="`mdp-grid-row-${index}`"
            >
                <div
                    v-for="item in props.header"
                    :key="item.id"
                    :class="{
                        'fixed-left': item.isFixed,
                    }"
                    :data-test="`mdp-grid-row-cell-${(row as Profile).student.id}-${item.key}`"
                >
                    <!-- Use dynamic slot based on header type, fallback to default content -->
                    <slot :name="item.type" :row="row" :header="item" :row-index="index">
                        <!-- Default content if no slot is provided -->
                        {{ row[item.key] }}
                    </slot>
                </div>
            </div>
        </template>
    </div>
</template>

<style scoped lang="scss">
.mdp-grid {
    display: grid;
    grid-template-columns: v-bind(gridTemplateColumns);
    position: relative;
    width: max-content;
}

.mdp-grid__row {
    display: contents; // Allow the children to be displayed as a grid
    > div {
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
    &--header > div {
        border-top-width: 0px;
        color: #000;
        font-weight: 600;
        padding: 0.625rem;
        white-space: nowrap;
    }
}

.fixed-top.fixed-left {
    z-index: calc(var(--sbx-depth-ui-sticky) + 3); // On top of both the left column, the header, and the dropdowns
}

.fixed-left {
    left: 0;
    position: sticky;
    z-index: var(--sbx-depth-ui-sticky); // Behind the dropdowns and table header, but on top of the content
}

.fixed-top {
    position: sticky;
    top: 0;
    z-index: calc(var(--sbx-depth-ui-sticky) + 2); // On top of both the left column and the dropdowns
}
</style>
