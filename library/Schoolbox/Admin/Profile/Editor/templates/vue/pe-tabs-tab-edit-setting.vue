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
 * The tab setting definition for the current tab and tab setting key
 */
const tabSettingDefinition = window.Vue.computed(() => {
    return peStore.tabSettings[props.tabSettingKey];
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
    // @ts-expect-error -- When a setting is added after the page is loaded, we need to tell foundation to fix the tooltips
    // eslint-disable-next-line @typescript-eslint/no-unsafe-call
    $(document).foundation("tooltip", "reflow");
});
</script>

<template>
    <div>
        <label>
            {{ tabSettingDefinition.displayAs }}
            <span data-tooltip class="has-tip tip-top hint" :title="tabSettingDefinition.tooltip"> ? </span>
        </label>
        <div class="checklist">
            <ul class="option-list">
                <li v-for="tabSettingOption in tabSettingDefinition.options" :key="tabSettingOption.id">
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
                    </label>
                </li>
            </ul>
        </div>
    </div>
</template>

<style scoped></style>
