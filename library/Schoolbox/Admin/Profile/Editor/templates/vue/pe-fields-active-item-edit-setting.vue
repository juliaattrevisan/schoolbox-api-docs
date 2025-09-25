<script setup lang="ts">
/**
 * A list of field settings for an active (selected or mandatory) field
 */

import { useProfileEditorStore } from "./store/profileEditorStore";
import { useProfileEditorFieldsStore } from "./store/profileEditorFieldsStore";
import type {
    ProfileEditorFieldSettingKey,
    ProfileEditorFieldSettingOption,
    ProfileEditorActiveField,
} from "./types/profileEditorTypes";

interface Props {
    field: ProfileEditorActiveField;
    fieldSettingKey: ProfileEditorFieldSettingKey;
}

const props = defineProps<Props>();
const peStore = useProfileEditorStore();
const peFieldsStore = useProfileEditorFieldsStore();

/**
 * Get the key to use in the label:for attribute for a field setting option
 * Also used as the data-test attribute for testing
 * @param fieldSettingOption - the field setting option
 * @returns the identifier
 */
function getLabelKeyForOption(fieldSettingOption: ProfileEditorFieldSettingOption): string {
    const baseKey = peFieldsStore.getFieldIdentifier(props.field);
    return `${baseKey}-setting-${props.fieldSettingKey}-option-${fieldSettingOption.id}`;
}

/**
 * Get the meta text for a field setting option
 * @param fieldSettingOption - the field setting option
 * @returns the meta text, or null if there is none
 */
function getMetaTextForOption(fieldSettingOption: ProfileEditorFieldSettingOption): string | null {
    if (!peStore.selectedRoleType) return null;
    if (!fieldSettingOption.meta) return null;
    if (typeof fieldSettingOption.meta === "string") return fieldSettingOption.meta;
    return fieldSettingOption.meta[peStore.selectedRoleType.roleType] || null;
}

/**
 * The field setting definition for the current field and field setting key
 */
const fieldSettingDefinition = window.Vue.computed(() => {
    return peStore.fieldSettings[props.fieldSettingKey];
});

/**
 * The available options for the current field setting
 * Split into the type of option (checkbox or input)
 */
const availableFieldSettingOptions = window.Vue.computed(() => {
    if (fieldSettingDefinition.value === undefined) {
        return { length: 0, checkbox: [], input: [] };
    }

    const availableOptions = fieldSettingDefinition.value.options.filter((option) => {
        const availability = props.field.availableFieldSettings[props.fieldSettingKey];
        if (availability === undefined) {
            return false;
        }
        if (typeof availability === "boolean") {
            return availability;
        }
        return availability[option.id];
    });

    return {
        length: availableOptions.length,
        checkbox: availableOptions.filter((option) => option.type === "checkbox"),
        input: availableOptions.filter((option) => option.type !== "checkbox"), // All other types (text/number) are inputs
    };
});

/**
 * When a field setting option is changed
 * @param fieldSettingOption - the field setting option
 * @param isChecked - whether the option is checked
 */
function onFieldSettingOptionChange(fieldSettingOption: ProfileEditorFieldSettingOption, isChecked: boolean): void {
    peFieldsStore.updateFieldSettingOption(props.field.field, props.fieldSettingKey, fieldSettingOption.id, isChecked);
}

window.Vue.onMounted(() => {
    // @ts-expect-error -- When a setting is added after the page is loaded, we need to tell foundation to fix the tooltips
    // eslint-disable-next-line @typescript-eslint/no-unsafe-call -- foundation isn't typed, see above
    $(document).foundation("tooltip", "reflow");
});
</script>

<template>
    <div v-if="fieldSettingDefinition && availableFieldSettingOptions.length" class="l-flex__item--1-grow">
        <!-- only show the label if there are checkboxes, as each input has its own label instead -->
        <label v-if="availableFieldSettingOptions.checkbox.length">
            {{ fieldSettingDefinition.displayAs }}
            <span
                v-if="fieldSettingDefinition.tooltip"
                data-tooltip
                class="has-tip tip-top hint"
                :title="fieldSettingDefinition.tooltip"
            >
                ?
            </span>
        </label>
        <div v-if="availableFieldSettingOptions.checkbox.length" class="checklist">
            <ul class="option-list">
                <li v-for="checkboxOption in availableFieldSettingOptions.checkbox" :key="checkboxOption.id">
                    <input
                        :id="getLabelKeyForOption(checkboxOption)"
                        type="checkbox"
                        :checked="Boolean(props.field.fieldSettings[props.fieldSettingKey][checkboxOption.id])"
                        @change="
                            onFieldSettingOptionChange(checkboxOption, ($event.target as HTMLInputElement).checked)
                        "
                    />
                    <label
                        :for="getLabelKeyForOption(checkboxOption)"
                        :data-test="getLabelKeyForOption(checkboxOption)"
                    >
                        {{ checkboxOption.name }}
                        <p v-if="getMetaTextForOption(checkboxOption)" class="meta">
                            {{ getMetaTextForOption(checkboxOption) }}
                        </p>
                    </label>
                </li>
            </ul>
        </div>

        <template v-for="(inputOption, i) in availableFieldSettingOptions.input" :key="inputOption.id">
            <label :for="getLabelKeyForOption(inputOption)" :class="{ 'l-mar-s--top': i > 0 }">
                {{ inputOption.name }}
                <span
                    v-if="getMetaTextForOption(inputOption)"
                    data-tooltip
                    class="has-tip tip-top hint"
                    :title="getMetaTextForOption(inputOption)!"
                >
                    ?
                </span>
            </label>
            <input
                :id="getLabelKeyForOption(inputOption)"
                :type="inputOption.type"
                :name="String(inputOption.id)"
                :value="props.field.fieldSettings[props.fieldSettingKey][inputOption.id]"
                @input="
                    peFieldsStore.updateFieldSettingOption(
                        props.field.field,
                        props.fieldSettingKey,
                        inputOption.id,
                        ($event.target as HTMLInputElement).value,
                    )
                "
            />
        </template>
    </div>
</template>

<style scoped></style>
