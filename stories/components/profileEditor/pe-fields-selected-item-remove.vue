<script setup lang="ts">
/**
 * The remove panel for a selected field
 */

import { slideDownOnEnter, slideUpOnLeave } from "@schoolboxVue/transitions/transitions";
import { useProfileEditorStore } from "./store/profileEditorStore";
import type { ProfileSelectedField } from "./types/profileTypes";

interface Props {
    field: ProfileSelectedField;
    isOpen: boolean;
}

const props = defineProps<Props>();
const peStore = useProfileEditorStore();
const emit = defineEmits(["close"]);

/**
 * When the user clicks the remove button
 */
function onRemoveClicked(): void {
    if (peStore.selectedRoleType === null) return;

    peStore.selectedRoleType.fields.selected = peStore.selectedRoleType.fields.selected.filter(
        (field) => field.field !== props.field.field,
    );
}
</script>

<template>
    <Transition @enter="slideDownOnEnter" @leave="slideUpOnLeave">
        <div v-if="props.isOpen" class="editPanel small-12">
            <section>
                <div>
                    <p>Are you sure you want to remove this field?</p>
                    <ul class="flex-list buttons">
                        <li><button class="button submit" @click="onRemoveClicked">Remove</button></li>
                        <li><button class="button" @click="emit('close')">Cancel</button></li>
                    </ul>
                </div>
            </section>
        </div>
    </Transition>
</template>

<style scoped></style>
