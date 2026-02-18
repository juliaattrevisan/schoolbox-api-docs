<script setup lang="ts">
import { cloneDeep } from "es-toolkit";
import { apiRequest } from "@js/util/sbx/apiUtils";
import { sbProps } from "@js/util/sbx/pageUtils";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import DacForm from "./dac-form.vue";
import DacSandbox from "./dac-sandbox.vue";
import type {
    DialogAgentAssessmentConfig,
    DialogAgentConfigureApiResponse,
    DialogAgentHelpfulness,
    DialogAgentScenario,
    DialogAgentScenarioField,
} from "./dacTypes";

interface Props {
    assessmentTitle: string;
    scenario: DialogAgentScenario;
    assessmentConfig: DialogAgentAssessmentConfig;
    helpfulnessOptions: { value: DialogAgentHelpfulness; label: string }[];
    languageOptions: string[];
    differentiationProfilesEnabled?: boolean;
    modifyRoute: string | null;
    postUpdate: string | null;
    getGenerateFields: string | null;
}

const props = defineProps<Props>();

const assessmentConfigInfo = window.Vue.ref<DialogAgentAssessmentConfig>(cloneDeep(props.assessmentConfig));
const isSubmitting = window.Vue.ref<boolean>(false);

const isPromptModalOpen = window.Vue.ref<boolean>(false);

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
 * @param data - The data returned from the form submission
 */
function updateSandbox(data: DialogAgentConfigureApiResponse): void {
    sandboxDataCache.value = cloneDeep(assessmentConfigInfo.value);
    sandboxDataCache.value.openingMessage = data.openingMessage;
    sandboxRef.value?.resetFeed();
}

const isGeneratingFields = window.Vue.ref<boolean>(false);
const generateFieldsError = window.Vue.ref<string | null>(null);

type ApiGenerateConfigurationFieldsResponse = {
    openingMessage: string | null;
    fields: Record<DialogAgentScenarioField["id"], string | null>;
};

/**
 * Request AI for an initial field configuration for a dialog agent activity to be generated
 */
async function generateFields(): Promise<void> {
    if (!props.getGenerateFields) {
        throw new Error("missing getGenerateFields route"); // shouldn't get here
    }

    generateFieldsError.value = null;
    isGeneratingFields.value = true;
    const response = await apiRequest<ApiGenerateConfigurationFieldsResponse>({
        method: "GET",
        url: props.getGenerateFields,
    });
    isGeneratingFields.value = false;

    if (!response.success) {
        generateFieldsError.value = response.message;
        return;
    }

    /** Update all the fields if there's relevant data for them */
    assessmentConfigInfo.value.fieldContexts.forEach((fieldContext) => {
        const generatedContent = response.data.fields[fieldContext.field.id] || null;
        if (generatedContent === null) {
            console.warn(`Missing content for field context ${fieldContext.id} (field ${fieldContext.field.id})`);
            return;
        }

        fieldContext.body = generatedContent;
    });

    /** Update the opening message if there's relevant data for it */
    if (response.data.openingMessage !== null) {
        assessmentConfigInfo.value.openingMessage = response.data.openingMessage;
    }
}

window.Vue.onMounted(async () => {
    /**
     * On initial load, if there aren't values for the fields yet then request AI to generate them
     * This only happens if the scenario fields are all empty
     *
     * "Rules" is prefilled by the scenario's default
     * "Opening Message" is left empty for now
     */
    if (props.getGenerateFields && assessmentConfigInfo.value.fieldContexts.every((fc) => fc.body.trim() === "")) {
        await generateFields();
    }
});
</script>

<template>
    <div class="row actions-small-1">
        <div class="small-12 columns">
            <h1>Configure {{ props.assessmentTitle }}</h1>
        </div>
        <nav>
            <sbx-pop-out content-id="header-options"></sbx-pop-out>
        </nav>
        <ul id="header-options" class="f-dropdown" data-dropdown-content aria-hidden="true">
            <li v-if="modifyRoute">
                <a class="icon-modify" :href="modifyRoute"> Modify Settings </a>
            </li>
            <li>
                <button class="icon-ai" @click="isPromptModalOpen = true">View Agent Prompt</button>
            </li>
        </ul>
    </div>
    <div v-if="isGeneratingFields" class="row">
        <div class="small-12 island">
            <section>
                <div class="loader"></div>

                <p class="text-center">Setting up scenario content...</p>
            </section>
        </div>
    </div>
    <div v-else class="row">
        <div class="small-12 island" :class="{ 'large-6': sandboxVisible }">
            <h2 class="subheader">Settings</h2>
            <div v-if="generateFieldsError" data-alert class="alert-box warning">
                Unable to generate initial field configuration: {{ generateFieldsError }}
                <a href="" class="close" @click.prevent="generateFieldsError = null">×</a>
            </div>
            <dac-form
                v-model:assessment-config="assessmentConfigInfo"
                v-model:is-submitting="isSubmitting"
                :scenario="props.scenario"
                :helpfulness-options="props.helpfulnessOptions"
                :language-options="props.languageOptions"
                :differentiation-profiles-enabled="props.differentiationProfilesEnabled"
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

    <basic-modal v-model:is-open="isPromptModalOpen" :has-background-click="true">
        <h2>Agent Prompt</h2>

        <template v-if="assessmentConfigInfo.prompt">
            <p>The following prompt has been created for the agent based on the current configuration:</p>
            <blockquote class="dac-prompt-preview">{{ assessmentConfigInfo.prompt }}</blockquote>
        </template>
        <p v-else>No prompt has been created yet, save the configuration first to create one.</p>

        <button class="button full-width" @click="isPromptModalOpen = false">Close</button>
    </basic-modal>
</template>

<style scoped lang="scss">
.dac-prompt-preview {
    max-height: 50vh;
    overflow-y: auto;
    white-space: pre-wrap;
}
</style>
