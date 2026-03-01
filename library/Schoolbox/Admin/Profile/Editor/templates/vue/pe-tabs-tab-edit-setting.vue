<script setup lang="ts">
/**
 * A list of tab settings for a profile tab
 */

import { useProfileEditorStore } from "./store/profileEditorStore";
import { useProfileEditorTabsStore } from "./store/profileEditorTabsStore";
import type {
    ProfileEditorTabSettingKey,
    ProfileEditorTabSettingOption,
    ProfileEditorTab,
} from "./types/profileEditorTypes";

interface Props {
    tab: ProfileEditorTab;
    tabSettingKey: ProfileEditorTabSettingKey;
}

const props = defineProps<Props>();
const peStore = useProfileEditorStore();
const peTabsStore = useProfileEditorTabsStore();

/**
 * Get the key to use in the label:for attribute for a tab setting option
 * Also used as the data-test attribute for testing
 * @param tabSettingOption - the tab setting option
 * @returns the identifier
 */
function getLabelKeyForOption(tabSettingOption: ProfileEditorTabSettingOption): string {
    const baseKey = peTabsStore.getTabIdentifier(props.tab.id);
    return `${baseKey}-setting-${props.tabSettingKey}-option-${tabSettingOption.id}`;
}

/**
 * Get the meta text for a tab setting option
 * @param tabSettingOption - the tab setting option
 * @returns the meta text, or null if there is none
 */
function getMetaTextForOption(tabSettingOption: ProfileEditorTabSettingOption): string | null {
    if (!peStore.selectedRoleType) return null;
    if (!tabSettingOption.meta) return null;
    if (typeof tabSettingOption.meta === "string") return tabSettingOption.meta;
    return tabSettingOption.meta[peStore.selectedRoleType.roleType] || null;
}

/**
 * The tab setting definition for the current tab and tab setting key
 */
const tabSettingDefinition = window.Vue.computed(() => {
    return peStore.tabSettings[props.tabSettingKey];
});

/**
 * The available options for the current tab setting
 */
const availableTabSettingOptions = window.Vue.computed(() => {
    if (tabSettingDefinition.value === undefined) {
        return [];
    }
    return tabSettingDefinition.value.options.filter((option) => {
        const availability = props.tab.availableTabSettings[props.tabSettingKey];
        if (availability === undefined) {
            return false;
        }
        if (typeof availability === "boolean") {
            return availability;
        }
        return availability[option.id];
    });
});

/**
 * When a tab setting option is changed
 * @param tabSettingOption - the tab setting option
 * @param isChecked - whether the option is checked
 */
function onTabSettingOptionChange(tabSettingOption: ProfileEditorTabSettingOption, isChecked: boolean): void {
    peTabsStore.updateTabSettingOption(props.tab.id, props.tabSettingKey, tabSettingOption.id, isChecked);
}

window.Vue.onMounted(() => {
    // When a setting is added after the page is loaded, we need to tell foundation to fix the tooltips
    window.jQuery(document).foundation("tooltip", "reflow");
});
</script>

<template>
    <div v-if="tabSettingDefinition">
        <label>
            {{ tabSettingDefinition.displayAs }}
            <span
                v-if="tabSettingDefinition.tooltip"
                data-tooltip
                class="has-tip tip-top hint"
                :title="tabSettingDefinition.tooltip"
            >
                ?
            </span>
        </label>
        <div class="checklist">
            <ul class="option-list">
                <li v-for="tabSettingOption in availableTabSettingOptions" :key="tabSettingOption.id">
                    <input
                        :id="getLabelKeyForOption(tabSettingOption)"
                        type="checkbox"
                        :checked="props.tab.tabSettings[props.tabSettingKey].includes(tabSettingOption.id)"
                        @change="
                            onTabSettingOptionChange(tabSettingOption, ($event.target as HTMLInputElement).checked)
                        "
                    />
                    <label
                        :for="getLabelKeyForOption(tabSettingOption)"
                        :data-test="getLabelKeyForOption(tabSettingOption)"
                    >
                        {{ tabSettingOption.name }}
                        <p v-if="getMetaTextForOption(tabSettingOption)" class="meta">
                            {{ getMetaTextForOption(tabSettingOption) }}
                        </p>
                    </label>
                </li>
            </ul>
        </div>
    </div>
</template>

<style scoped></style>
