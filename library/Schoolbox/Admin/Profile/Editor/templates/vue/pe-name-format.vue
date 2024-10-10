<script setup lang="ts">
/**
 * To customise the name format for the current role type's user profile page
 */
import * as toast from "@schoolboxVue/components/toast/sbxToastEvent";
import { slideDownOnEnter, slideUpOnLeave } from "@schoolboxVue/transitions/transitions";
import { useProfileEditorStore } from "./store/profileEditorStore";

const peStore = useProfileEditorStore();

const isSaving = window.Vue.ref(false);
const saveError = window.Vue.ref<string | null>(null);

/**
 * Whether the current role type's name format is unsaved
 * @returns true if the name format has changed, otherwise false
 */
function isNameFormatUnsaved(): boolean {
    if (peStore.selectedRoleType === null) return false;
    if (peStore.savedSelectedRoleType === null) return false;

    return peStore.selectedRoleType.nameFormat.trim() !== peStore.savedSelectedRoleType.nameFormat.trim();
}

/**
 * When the user clicks the update button
 */
async function onUpdateClicked(): Promise<void> {
    if (isSaving.value) return;
    saveError.value = null;
    isSaving.value = true;
    toast.activate("Updating name format...");
    const saveResult = await peStore.saveNameFormat();
    if (saveResult !== true) {
        saveError.value = saveResult;
        toast.timeout({ text: `Failed to update name format: ${saveResult}`, isSuccess: false });
    }
    isSaving.value = false;
    toast.timeout({ text: "Name format updated", isSuccess: true });
}
</script>

<template>
    <div v-if="peStore.selectedRoleType" class="l-mar-m--top">
        <input v-model="peStore.selectedRoleType.nameFormat" type="text" />
        <Transition @enter="slideDownOnEnter" @leave="slideUpOnLeave">
            <div v-if="isNameFormatUnsaved()">
                <button class="button" :disabled="isSaving" @change="saveError = null" @click="onUpdateClicked">
                    {{ isSaving ? "Updating..." : "Update" }}
                </button>
            </div>
        </Transition>
        <Transition @enter="slideDownOnEnter" @leave="slideUpOnLeave">
            <div v-if="saveError" class="alert alert-box" style="opacity: 1">
                <b>Warning:</b> There was a problem saving changes to the name format. Please
                <a href="">refresh your page</a> to ensure you're up to date.
                <br />
                {{ saveError }}
            </div>
        </Transition>
    </div>
</template>

<style scoped>
input {
    width: 100%;
    margin-bottom: 0px;
}

button {
    width: 100%;
}
</style>
