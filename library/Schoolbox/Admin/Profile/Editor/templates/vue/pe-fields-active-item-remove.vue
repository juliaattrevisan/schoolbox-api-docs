<script setup lang="ts">
/**
 * The remove panel for an active field
 */

import { slideDownOnEnter, slideUpOnLeave } from "@schoolboxVue/transitions/transitions";
import { useProfileEditorFieldsStore } from "./store/profileEditorFieldsStore";
import type { ProfileEditorActiveField } from "./types/profileEditorTypes";

interface Props {
    field: ProfileEditorActiveField;
    isOpen: boolean;
}

const props = defineProps<Props>();
const peFieldsStore = useProfileEditorFieldsStore();
const emit = defineEmits(["close"]);

const isRemoving = window.Vue.ref(false);

/**
 * When the user clicks the remove button
 */
async function onRemoveClicked(): Promise<void> {
    isRemoving.value = true;
    await peFieldsStore.disableField(props.field.field);
    isRemoving.value = false;
}
</script>

<template>
    <Transition @enter="slideDownOnEnter" @leave="slideUpOnLeave">
        <div v-if="props.isOpen" class="column content small-12">
            <p>Are you sure you want to remove this field?</p>
            <ul class="flex-list buttons">
                <li>
                    <button class="button submit" :disabled="isRemoving" @click="onRemoveClicked">
                        {{ isRemoving ? "Removing..." : "Remove" }}
                    </button>
                </li>
                <li><button class="button" @click="emit('close')">Cancel</button></li>
            </ul>
        </div>
    </Transition>
</template>

<style scoped></style>
