<script setup lang="ts">
/**
 * A list of field settings for a selected field
 */

import { useProfileEditorStore } from "./store/profileEditorStore";
import type {
    ProfileEditorFieldSettingKey,
    ProfileEditorFieldSettingOption,
    ProfileEditorSelectedField,
} from "./profileEditorTypes";

interface Props {
    field: ProfileEditorSelectedField;
    fieldSettingKey: ProfileEditorFieldSettingKey;
}

const props = defineProps<Props>();
const peStore = useProfileEditorStore();

/**
 * Get the key to use in the label:for attribute for a field setting option
 * @param fieldSettingOption - the field setting option
 * @returns the identifier
 */
function getLabelKeyForOption(fieldSettingOption: ProfileEditorFieldSettingOption): string {
    return (
        `pe-${peStore.selectedRoleType?.roleType || "-"}-fields-selected-item` +
        `-${props.field.field}-setting-${props.fieldSettingKey}-option-${fieldSettingOption.id}`
    );
}

/**
 * The field setting definition for the current field and field setting key
 */
const fieldSettingDefinition = window.Vue.computed(() => {
    return peStore.fieldSettings[props.fieldSettingKey];
});

/**
 * When a field setting option is changed
 * @param fieldSettingOption - the field setting option
 * @param isChecked - whether the option is checked
 */
function onFieldSettingOptionChange(fieldSettingOption: ProfileEditorFieldSettingOption, isChecked: boolean): void {
    if (peStore.selectedRoleType === null) return;

    const storeField = peStore.selectedRoleType.fields.selected.find((field) => field.field === props.field.field);
    if (storeField === undefined) throw new Error("Field not found in store");

    const filteredSettings = storeField.fieldSettings[props.fieldSettingKey].filter(
        (id) => id !== fieldSettingOption.id,
    );

    if (isChecked) {
        storeField.fieldSettings[props.fieldSettingKey] = [...filteredSettings, fieldSettingOption.id];
    } else {
        storeField.fieldSettings[props.fieldSettingKey] = filteredSettings;
    }
}

window.Vue.onMounted(() => {
    // @ts-expect-error -- When a setting is added after the page is loaded, we need to tell foundation to fix the tooltips
    // eslint-disable-next-line @typescript-eslint/no-unsafe-call
    $(document).foundation("tooltip", "reflow");
});
</script>

<template>
    <div class="small-12 medium-6">
        <label>
            {{ fieldSettingDefinition.displayAs }}:
            <span data-tooltip class="has-tip tip-top hint" :title="fieldSettingDefinition.tooltip"> ? </span>
        </label>
        <div class="checklist">
            <ul class="option-list">
                <li v-for="fieldSettingOption in fieldSettingDefinition.options" :key="fieldSettingOption.id">
                    <input
                        :id="getLabelKeyForOption(fieldSettingOption)"
                        type="checkbox"
                        :checked="props.field.fieldSettings[props.fieldSettingKey].includes(fieldSettingOption.id)"
                        @change="
                            onFieldSettingOptionChange(fieldSettingOption, ($event.target as HTMLInputElement).checked)
                        "
                    />
                    <label :for="getLabelKeyForOption(fieldSettingOption)">
                        {{ fieldSettingOption.name }}
                    </label>
                </li>
            </ul>
        </div>
    </div>
</template>

<style scoped></style>
