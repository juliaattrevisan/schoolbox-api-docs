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

    return peStore.selectedRoleType.nameFormat !== peStore.savedSelectedRoleType.nameFormat;
}

/**
 * When the user clicks the update button
 */
async function onUpdateClicked(): Promise<void> {
    if (isSaving.value) return;
    saveError.value = null;
    isSaving.value = true;
    toast.activate("Updating name format...");
    if (isNameFormatUnsaved()) {
        const saveResult = await peStore.saveNameFormat();
        if (saveResult !== true) {
            saveError.value = saveResult;
            toast.timeout({ text: `Failed to update name format: ${saveResult}`, isSuccess: false });
        }
    }
    isSaving.value = false;
    toast.timeout({ text: "Name format updated", isSuccess: true });
}
</script>

<template>
    <section v-if="peStore.selectedRoleType" class="content">
        <label for="pe-name-format">Name Display Options</label>
        <p class="meta">
            Insert a default format for how user names will be displayed. Can contain: {title}, {firstname},
            {preferredname} (falls back to first name if empty), {lastname}, {pronoun}.
        </p>
        <input
            id="pe-name-format"
            v-model="peStore.selectedRoleType.nameFormat"
            type="text"
            placeholder="Enter a name display format"
        />
        <button class="button submit" :disabled="isSaving" @change="saveError = null" @click="onUpdateClicked">
            {{ isSaving ? "Updating..." : "Update" }}
        </button>
        <Transition @enter="slideDownOnEnter" @leave="slideUpOnLeave">
            <div v-if="saveError" class="alert alert-box" style="opacity: 1">
                <b>Warning:</b> There was a problem saving changes to the name format. Please
                <a href="">refresh your page</a> to ensure you're up to date.
                <br />
                {{ saveError }}
            </div>
        </Transition>
    </section>
</template>

<style scoped></style>
