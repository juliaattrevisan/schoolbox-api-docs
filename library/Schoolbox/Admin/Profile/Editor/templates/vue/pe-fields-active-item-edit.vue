<script setup lang="ts">
/**
 * The edit panel for an active field
 */

import { slideDownOnEnter, slideUpOnLeave } from "@schoolboxVue/transitions/transitions";
import PeFieldsActiveItemEditSetting from "./pe-fields-active-item-edit-setting.vue";
import { useProfileEditorStore } from "./store/profileEditorStore";
import { useProfileEditorFieldsStore } from "./store/profileEditorFieldsStore";
import type { ProfileEditorActiveField } from "./types/profileEditorTypes";

interface Props {
    field: ProfileEditorActiveField;
    isOpen: boolean;
}

const props = defineProps<Props>();
const peStore = useProfileEditorStore();
const peFieldsStore = useProfileEditorFieldsStore();
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
    await peFieldsStore.saveFieldSettings(props.field.field);
    isUpdating.value = false;

    emit("close");
}
</script>

<template>
    <Transition @enter="slideDownOnEnter" @leave="slideUpOnLeave">
        <div v-if="props.isOpen" class="column content small-12">
            <div class="l-grid l-grid--12 l-gap-m l-pad-s">
                <pe-fields-active-item-edit-setting
                    v-for="fieldSettingKey in validFieldSettingKeys"
                    :key="fieldSettingKey"
                    :field="props.field"
                    :field-setting-key="fieldSettingKey"
                />
            </div>
            <Transition @enter="slideDownOnEnter" @leave="slideUpOnLeave">
                <div v-if="peFieldsStore.isFieldSettingsUnsaved(props.field.field)">
                    <input
                        type="submit"
                        :value="isUpdating ? 'Updating...' : 'Update'"
                        :disabled="isUpdating"
                        :data-test="`${peFieldsStore.getFieldIdentifier(props.field)}-settings-confirm`"
                        @click="onUpdateClicked"
                    />
                </div>
            </Transition>
        </div>
    </Transition>
</template>

<style scoped></style>
