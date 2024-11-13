<script setup lang="ts">
/**
 * A specific (sortable) tab in the tabs list
 */

import { animateRotation } from "@js/util/animateUtils";
import PeTabsTabEdit from "./pe-tabs-tab-edit.vue";
import type { ProfileEditorTab } from "./types/profileEditorTypes";

interface Props {
    // The tab to display
    tab: ProfileEditorTab;
    // Is the tab currently being dragged?
    dragging: boolean;
}

const props = defineProps<Props>();
const editBtn = window.Vue.ref<HTMLElement | null>(null);

const isEditPanelOpen = window.Vue.ref<boolean>(false);

window.Vue.watch(isEditPanelOpen, (isOpen) => {
    // Animate the edit button
    if (editBtn.value) {
        const ANIMATE_CLOSED_POSITION = 0;
        const ANIMATE_OPEN_POSITION = 90;
        const ANIMATE_ORIGIN = "20px 20px";
        const ANIMATE_SPEED = 0.3;
        animateRotation(
            editBtn.value,
            isOpen ? ANIMATE_OPEN_POSITION : ANIMATE_CLOSED_POSITION,
            ANIMATE_ORIGIN,
            ANIMATE_SPEED,
        );
    }
});
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
                <a ref="editBtn" class="icon-settings" title="Settings" @click="isEditPanelOpen = !isEditPanelOpen" />
            </nav>
        </div>

        <div class="pe-tabs-tab-panel">
            <pe-tabs-tab-edit
                :tab="props.tab"
                :is-open="isEditPanelOpen && !props.dragging"
                @close="isEditPanelOpen = false"
            />
        </div>
    </div>
</template>

<style scoped lang="scss">
.pe-tabs-tab {
    background-color: white;
    height: min-content; /* Prevent another tab from expanding this one */
    min-width: 18rem; /* Prevent the tab from being too narrow */

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

    .list-item > .card {
        padding: 0; /* Remove padding for compactness, there is padding already elsewhere */
    }

    /* To ensure the height of the title matches the card.compact height */
    h3 {
        min-height: 2.5rem;
    }

    /* To remove the hover background on the icons since they rotate */
    nav > a[class^="icon-"]:hover {
        background-color: transparent;
    }

    /* The item held by the mouse when dragging */
    &.dragged-item {
        background-color: var(--content-ui-hover);
        height: auto !important;

        /*
         * Hide any component panel when this is the item held by the mouse.
         * This must be a style since the element handling the drag will not react to changes here
         */
        .pe-tabs-tab-panel {
            display: none;
        }
    }
}
</style>
