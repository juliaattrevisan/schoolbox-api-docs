<script setup lang="ts">
/**
 * An individual selected field in the profile editor
 */

import PeFieldsSelectedItemEdit from "./pe-fields-selected-item-edit.vue";
import PeFieldsSelectedItemRemove from "./pe-fields-selected-item-remove.vue";
import type { ProfileSelectedField } from "./types/profileTypes";

interface Props {
    field: ProfileSelectedField;
}

const props = defineProps<Props>();

const isEditPanelOpen = window.Vue.ref<boolean>(false);
const isRemovePanelOpen = window.Vue.ref<boolean>(false);
</script>

<template>
    <div class="component l-mar-m--bottom">
        <div class="row collapse">
            <div class="small-12 columns">
                <div class="component-titlebar">
                    <div class="actions-small-2">
                        <div class="list-item">
                            <h2 :title="props.field.displayAs">
                                <a
                                    href="#"
                                    class="icon-grip"
                                    style="user-select: none; touch-action: none; cursor: grab"
                                />
                                {{ props.field.displayAs }}
                            </h2>
                        </div>
                        <nav>
                            <a
                                href="#"
                                class="icon-settings settings edit"
                                title="Settings"
                                @click="
                                    isEditPanelOpen = !isEditPanelOpen;
                                    isRemovePanelOpen = false;
                                "
                            />
                            <a
                                href="#"
                                class="icon-close"
                                title="Close"
                                @click="
                                    isRemovePanelOpen = !isRemovePanelOpen;
                                    isEditPanelOpen = false;
                                "
                            />
                        </nav>
                    </div>
                </div>

                <pe-fields-selected-item-edit
                    :field="props.field"
                    :is-open="isEditPanelOpen"
                    @close="isEditPanelOpen = false"
                />
                <pe-fields-selected-item-remove
                    :field="props.field"
                    :is-open="isRemovePanelOpen"
                    @close="isRemovePanelOpen = false"
                />
            </div>
        </div>
    </div>
</template>

<style scoped></style>
