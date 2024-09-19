<script setup lang="ts">
/**
 * A list of field settings for a selected field
 */

import { useProfileEditorStore } from "./store/profileEditorStore";
import type { ProfileFieldSetting, ProfileFieldSettingOption, ProfileSelectedField } from "./types/profileTypes";

interface Props {
    field: ProfileSelectedField;
    fieldSetting: ProfileFieldSetting;
}

const props = defineProps<Props>();
const peStore = useProfileEditorStore();

/**
 * Get the key to use in the label:for attribute for a field setting option
 * @param fieldSettingOption - the field setting option
 * @returns the identifier
 */
function getLabelKeyForOption(fieldSettingOption: ProfileFieldSettingOption): string {
    return (
        `pe-${peStore.selectedRoleType?.name || "-"}-fields-selected-item` +
        `-${props.field.field}-setting-${props.fieldSetting.key}-option-${fieldSettingOption.id}`
    );
}

/**
 * When a field setting option is changed
 * @param fieldSettingOption - the field setting option
 * @param isChecked - whether the option is checked
 */
function onFieldSettingOptionChange(fieldSettingOption: ProfileFieldSettingOption, isChecked: boolean): void {
    if (peStore.selectedRoleType === null) return;

    const storeField = peStore.selectedRoleType.fields.selected.find((field) => field.field === props.field.field);
    if (storeField === undefined) throw new Error("Field not found in store");

    const filteredSettings = storeField.fieldSettings[props.fieldSetting.key].filter(
        (id) => id !== fieldSettingOption.id,
    );

    if (isChecked) {
        storeField.fieldSettings[props.fieldSetting.key] = [...filteredSettings, fieldSettingOption.id];
    } else {
        storeField.fieldSettings[props.fieldSetting.key] = filteredSettings;
    }
}
</script>

<template>
    <div class="small-12 medium-6">
        <label>{{ fieldSetting.displayAs }}:</label>
        <div class="checklist">
            <ul class="option-list">
                <li v-for="fieldSettingOption in fieldSetting.options" :key="fieldSettingOption.id">
                    <input
                        :id="getLabelKeyForOption(fieldSettingOption)"
                        type="checkbox"
                        :checked="props.field.fieldSettings[props.fieldSetting.key].includes(fieldSettingOption.id)"
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
