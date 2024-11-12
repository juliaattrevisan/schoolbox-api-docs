<script setup lang="ts">
/**
 * The edit panel for a profile tab
 */

import { slideDownOnEnter, slideUpOnLeave } from "@schoolboxVue/transitions/transitions";
import PeTabsTabEditSetting from "./pe-tabs-tab-edit-setting.vue";
import { useProfileEditorStore } from "./store/profileEditorStore";
import { useProfileEditorTabsStore } from "./store/profileEditorTabsStore";
import type { ProfileEditorTab } from "./types/profileEditorTypes";

interface Props {
    tab: ProfileEditorTab;
    isOpen: boolean;
}

const props = defineProps<Props>();
const peStore = useProfileEditorStore();
const peTabsStore = useProfileEditorTabsStore();
const emit = defineEmits(["close"]);

/**
 * A list of all the tab setting keys that are valid for the current tab
 */
const validTabSettingKeys = window.Vue.computed(() => {
    const validKeysForTab = Object.keys(props.tab.tabSettings);
    return Object.keys(peStore.tabSettings).filter((tabSettingKey) => validKeysForTab.includes(tabSettingKey));
});

const isUpdating = window.Vue.ref(false);

/**
 * When the user clicks the update button
 */
async function onUpdateClicked(): Promise<void> {
    isUpdating.value = true;
    await peTabsStore.saveTabSettings(props.tab.id);
    isUpdating.value = false;

    emit("close");
}
</script>

<template>
    <Transition @enter="slideDownOnEnter" @leave="slideUpOnLeave">
        <div v-if="props.isOpen" class="column content small-12">
            <pe-tabs-tab-edit-setting
                v-for="tabSettingKey in validTabSettingKeys"
                :key="tabSettingKey"
                :tab="props.tab"
                :tab-setting-key="tabSettingKey"
            />
            <Transition @enter="slideDownOnEnter" @leave="slideUpOnLeave">
                <div v-if="peTabsStore.isTabSettingsUnsaved(props.tab.id)">
                    <input
                        type="submit"
                        :value="isUpdating ? 'Updating...' : 'Update'"
                        :disabled="isUpdating"
                        @click="onUpdateClicked"
                    />
                </div>
            </Transition>
        </div>
    </Transition>
</template>

<style scoped></style>
