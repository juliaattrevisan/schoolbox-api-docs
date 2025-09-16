<script setup lang="ts">
import { useMomentFormStore } from "./store/momentFormStore";

interface Props {
    /* A special label for the Publish button that changes to Approve
     * in exactly one workflow
     */
    publishLabel: string;
}

const props = defineProps<Props>();
const mfStore = useMomentFormStore();

/**
 * The relevant routes to use,
 * If this is a create form, createRoutes takes precedence
 * otherwise the routes from the existing moment are used
 */
const relevantRoutes = window.Vue.computed(() => {
    if (mfStore.createRoutes !== null) {
        return mfStore.createRoutes;
    }

    if (mfStore.moment !== null) {
        return mfStore.moment._links;
    }

    return {};
});

const isSaveSubmitPublishButtonDisabled = window.Vue.computed(
    () => mfStore.isSaving || mfStore.showInvalidEvidenceError,
);
</script>

<template>
    <ul class="flex-list buttons">
        <li v-if="relevantRoutes.draft">
            <button :disabled="isSaveSubmitPublishButtonDisabled" @click="() => mfStore.submitForm(false, 'draft')">
                Save for later
            </button>
        </li>
        <li v-if="relevantRoutes.reject">
            <button :disabled="mfStore.isSaving" @click="() => mfStore.reject()">Request update</button>
        </li>
        <li v-if="relevantRoutes.submit">
            <button
                :disabled="isSaveSubmitPublishButtonDisabled"
                class="submit"
                @click="() => mfStore.submitForm(false, 'submit')"
            >
                Submit for approval
            </button>
        </li>
        <li v-if="relevantRoutes.publish">
            <button :disabled="isSaveSubmitPublishButtonDisabled" class="submit" @click="() => mfStore.publish()">
                {{ props.publishLabel }}
            </button>
        </li>
    </ul>
</template>

<style scoped></style>
