<script setup lang="ts">
/**
 * The edit panel for a profile tab
 */

import { slideDownOnEnter, slideUpOnLeave } from "@schoolboxVue/transitions/transitions";
import PeTabsTabEditSetting from "./pe-tabs-tab-edit-setting.vue";
import { useProfileEditorTabsStore } from "./store/profileEditorTabsStore";
import type { ProfileEditorTab } from "./types/profileEditorTypes";

interface Props {
    tab: ProfileEditorTab;
    isOpen: boolean;
}

const props = defineProps<Props>();
const peTabsStore = useProfileEditorTabsStore();
const emit = defineEmits(["close"]);

const isUpdating = window.Vue.ref(false);

/**
 * When the user clicks the update button
 */
async function onUpdateClicked(): Promise<void> {
    if (peTabsStore.isTabSettingsUnsaved(props.tab.id)) {
        isUpdating.value = true;
        await peTabsStore.saveTabSettings(props.tab.id);
        isUpdating.value = false;
    }

    emit("close");
}
</script>

<template>
    <Transition @enter="slideDownOnEnter" @leave="slideUpOnLeave">
        <div v-if="props.isOpen" class="column content small-12">
            <pe-tabs-tab-edit-setting
                v-for="(_, tabSettingKey) in props.tab.availableTabSettings"
                :key="tabSettingKey"
                :tab="props.tab"
                :tab-setting-key="tabSettingKey"
            />
            <input
                type="submit"
                :value="isUpdating ? 'Updating...' : 'Update'"
                :disabled="isUpdating"
                :data-test="`${peTabsStore.getTabIdentifier(props.tab.id)}-settings-confirm`"
                @click="onUpdateClicked"
            />
        </div>
    </Transition>
</template>

<style scoped></style>
