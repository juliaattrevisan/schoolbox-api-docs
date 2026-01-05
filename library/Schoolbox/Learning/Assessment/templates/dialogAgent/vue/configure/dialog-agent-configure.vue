<script setup lang="ts">
import { cloneDeep } from "lodash";
import { sbProps } from "@js/util/sbx/pageUtils";
import DacForm from "./dac-form.vue";
import DacSandbox from "./dac-sandbox.vue";
import type { DialogAgentAssessmentConfig, DialogAgentHelpfulness, DialogAgentScenario } from "./dacTypes";

interface Props {
    scenario: DialogAgentScenario;
    assessmentConfig: DialogAgentAssessmentConfig;
    helpfulnessOptions: { value: DialogAgentHelpfulness; label: string }[];
    languageOptions: string[];
    postUpdate: string | null;
}

const props = defineProps<Props>();

const assessmentConfigInfo = window.Vue.ref<DialogAgentAssessmentConfig>(cloneDeep(props.assessmentConfig));
const isSubmitting = window.Vue.ref<boolean>(false);

/**
 * Whether the sandbox section is visible, not necessarily whether it can be used
 * The sandbox won't be visible if the dialog agent AI feature is disabled
 *
 * The undefined check is includes the case where the user can't see sbProps.aiFeatures (currently only staff can)
 * however, it's possible for a student to visit and use this page so if they do, they should see the sandbox if available.
 * If the feature isn't enabled for these users, they'll just see the empty state below instead of the sandbox. that's fine for now
 */
const sandboxVisible = sbProps.aiFeatures.dialogagent === undefined || sbProps.aiFeatures.dialogagent.enabled;

const sandboxRef = window.Vue.ref<InstanceType<typeof DacSandbox> | null>(null);

/**
 * The sandbox should warn if there are unsaved changes (and hence it's not actually reflecting the current config)
 */
const sandboxDataCache = window.Vue.ref(cloneDeep(assessmentConfigInfo.value));
const isSandboxDirty = window.Vue.computed(() => {
    return JSON.stringify(sandboxDataCache.value) !== JSON.stringify(assessmentConfigInfo.value);
});

/**
 * Update the sandbox cache when the form is successfully submitted
 */
function updateSandbox(): void {
    sandboxDataCache.value = cloneDeep(assessmentConfigInfo.value);
    sandboxRef.value?.resetFeed();
}
</script>

<template>
    <div class="row">
        <div class="small-12 island" :class="{ 'large-6': sandboxVisible }">
            <h2 class="subheader">Settings</h2>
            <dac-form
                v-model:assessment-config="assessmentConfigInfo"
                v-model:is-submitting="isSubmitting"
                :scenario="props.scenario"
                :helpfulness-options="props.helpfulnessOptions"
                :language-options="props.languageOptions"
                :post-update="props.postUpdate"
                @saved="updateSandbox"
            />
        </div>

        <div v-if="sandboxVisible" class="small-12 large-6 island">
            <h2 class="subheader">Sandbox</h2>

            <section v-if="isSubmitting">
                <div class="loader"></div>
            </section>
            <div
                v-else-if="!sandboxDataCache._routes.postSandboxMessage || !sandboxDataCache.openingMessage"
                class="empty-state"
            >
                <i class="icon-dialog-agent"></i>
                <p>Sandbox will be available once the agent configuration has been saved.</p>
            </div>
            <section v-else>
                <dac-sandbox
                    ref="sandboxRef"
                    :opening-message="sandboxDataCache.openingMessage"
                    :post-message="sandboxDataCache._routes.postSandboxMessage"
                    :is-dirty="isSandboxDirty"
                />
            </section>
        </div>
    </div>
</template>

<style scoped lang="scss"></style>
