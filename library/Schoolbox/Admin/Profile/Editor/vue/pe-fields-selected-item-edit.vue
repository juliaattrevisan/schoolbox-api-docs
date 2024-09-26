<script setup lang="ts">
/**
 * The edit panel for a selected field
 */

import { slideDownOnEnter, slideUpOnLeave } from "@schoolboxVue/transitions/transitions";
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

/**
 * A list of all the field setting keys that are valid for the current field
 */
const validFieldSettingKeys = window.Vue.computed(() => {
    const validKeysForField = Object.keys(props.field.fieldSettings);
    return Object.keys(peStore.fieldSettings).filter((fieldSettingKey) => validKeysForField.includes(fieldSettingKey));
});

const isUpdating = window.Vue.ref(false);

/**
 * When the user clicks the update button
 */
async function onUpdateClicked(): Promise<void> {
    isUpdating.value = true;

    await new Promise((resolve) => {
        setTimeout(resolve, 500);
    });

    isUpdating.value = false;

    emit("close");
}
</script>

<template>
    <Transition @enter="slideDownOnEnter" @leave="slideUpOnLeave">
        <div v-if="props.isOpen" class="editPanel small-12">
            <section>
                <div class="l-grid l-grid--12 l-gap-m">
                    <pe-fields-selected-item-edit-setting
                        v-for="fieldSettingKey in validFieldSettingKeys"
                        :key="fieldSettingKey"
                        :field="props.field"
                        :field-setting-key="fieldSettingKey"
                    />
                </div>
                <input
                    type="submit"
                    :value="isUpdating ? 'Updating...' : 'Update'"
                    :disabled="isUpdating"
                    @click="onUpdateClicked"
                />
            </section>
        </div>
    </Transition>
</template>

<style scoped></style>
