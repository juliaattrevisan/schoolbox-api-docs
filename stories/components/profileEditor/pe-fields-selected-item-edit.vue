<script setup lang="ts">
/**
 * The edit panel for a selected field
 */

import PeFieldsSelectedItemEditSetting from "./pe-fields-selected-item-edit-setting.vue";
import { useProfileEditorStore } from "./store/profileEditorStore";
import type { ProfileSelectedField } from "./types/profileTypes";

interface Props {
    field: ProfileSelectedField;
    isOpen: boolean;
}

const props = defineProps<Props>();
const peStore = useProfileEditorStore();
const emit = defineEmits(["close"]);

const fieldSettings = window.Vue.computed(() => {
    const validKeysForField = Object.keys(props.field.fieldSettings);
    return peStore.fieldSettings.filter((fieldSetting) => validKeysForField.includes(fieldSetting.key));
});

/**
 * When the user clicks the update button
 */
function onUpdateClicked(): void {
    emit("close");
}
</script>

<template>
    <div class="editPanel small-12" :style="{ display: props.isOpen ? 'block' : 'none' }">
        <section>
            <div class="l-grid l-grid--12 l-gap-m">
                <pe-fields-selected-item-edit-setting
                    v-for="fieldSetting in fieldSettings"
                    :key="fieldSetting.key"
                    :field="props.field"
                    :field-setting="fieldSetting"
                />
            </div>
            <input type="submit" value="Update" @click="onUpdateClicked" />
        </section>
    </div>
</template>

<style scoped></style>
