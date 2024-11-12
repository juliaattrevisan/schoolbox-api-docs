<script setup lang="ts">
/**
 * A specific (sortable) tab in the tabs list
 */
import type { ProfileEditorTab } from "./types/profileEditorTypes";

interface Props {
    // The tab to display
    tab: ProfileEditorTab;
    // Is the tab currently being dragged?
    dragging: boolean;
}

const props = defineProps<Props>();
</script>

<template>
    <div class="pe-tabs-tab">
        <div class="actions-small-1">
            <div class="icon-grip" data-pe-tabs-tab-drag-handle></div>
            <div class="list-item">
                <div class="card small-12 compact">
                    <h3>{{ props.tab.label }}</h3>
                </div>
            </div>
            <nav>
                <a class="icon-settings" title="Settings" />
            </nav>
        </div>
    </div>
</template>

<style scoped lang="scss">
.pe-tabs-tab {
    background-color: white;
    height: min-content; /* Prevent another tab from expanding this one */

    > .actions-small-1 {
        align-items: center;
        display: flex;
    }

    .icon-grip {
        cursor: grab;
        padding: 0 0.75rem;

        &::before {
            padding-right: 0; /* Override padding so the icon is positioned correctly */
        }
    }

    /* To ensure the height of the title matches the card.compact height */
    h3 {
        min-height: 2.5rem;
    }

    /* The item held by the mouse when dragging */
    &.dragged-item {
        background-color: var(--content-ui-hover);
        height: auto !important;
    }
}
</style>
