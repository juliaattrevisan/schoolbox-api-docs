<script setup lang="ts">
/**
 * The edit panel for an active field
 */

import { slideDownOnEnter, slideUpOnLeave } from "@schoolboxVue/transitions/transitions";
import PeFieldsActiveItemEditSetting from "./pe-fields-active-item-edit-setting.vue";
import { useProfileEditorFieldsStore } from "./store/profileEditorFieldsStore";
import type { ProfileEditorActiveField } from "./types/profileEditorTypes";

interface Props {
    field: ProfileEditorActiveField;
    isOpen: boolean;
}

const props = defineProps<Props>();
const peFieldsStore = useProfileEditorFieldsStore();
const emit = defineEmits(["close"]);

const isUpdating = window.Vue.ref(false);

/**
 * When the user clicks the update button
 */
async function onUpdateClicked(): Promise<void> {
    if (peFieldsStore.isFieldSettingsUnsaved(props.field.field)) {
        isUpdating.value = true;
        await peFieldsStore.saveFieldSettings(props.field.field);
        isUpdating.value = false;
    }

    emit("close");
}
</script>

<template>
    <Transition @enter="slideDownOnEnter" @leave="slideUpOnLeave">
        <div v-if="props.isOpen" class="column content small-12">
            <div class="l-flex-row l-gap-m l-pad-s">
                <pe-fields-active-item-edit-setting
                    v-for="(_, fieldSettingKey) in props.field.availableFieldSettings"
                    :key="fieldSettingKey"
                    :field="props.field"
                    :field-setting-key="fieldSettingKey"
                />
            </div>
            <input
                type="submit"
                :value="isUpdating ? 'Updating...' : 'Update'"
                :disabled="isUpdating"
                :data-test="`${peFieldsStore.getFieldIdentifier(props.field)}-settings-confirm`"
                @click="onUpdateClicked"
            />
        </div>
    </Transition>
</template>

<style scoped></style>
