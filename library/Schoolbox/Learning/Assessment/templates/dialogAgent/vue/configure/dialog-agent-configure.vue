<script setup lang="ts">
import { cloneDeep } from "lodash";
import { sbProps } from "@js/util/sbx/pageUtils";
import { apiRequest } from "@js/util/sbx/apiUtils";
import { getPageReturnUrl } from "@js/util/sbx/urlUtils";
import DacSandbox from "./dac-sandbox.vue";

type DialogAgentScenario = {
    title: string;
    description: string;
};

type DialogAgentFieldContext = {
    id: number;
    body: string;
    field: {
        id: number;
        title: string;
    };
};

type DialogAgentAssessmentConfig = {
    helpfulness: string | null;
    language: string | null;
    useDifferentiationProfile: boolean;
    openingMessage: string | null;
    rules: string | null;
    fieldContexts: DialogAgentFieldContext[];
    _routes: {
        postSandboxMessage: string | null;
    };
};

interface Props {
    scenario: DialogAgentScenario;
    assessmentConfig: DialogAgentAssessmentConfig;
    helpfulnessOptions: Record<string, string>;
    languageOptions: string[];
    postUpdate: string | null;
}

const props = defineProps<Props>();
const returnUrl = getPageReturnUrl();

const scenarioInfo = window.Vue.ref<DialogAgentScenario>(cloneDeep(props.scenario));
const assessmentConfigInfo = window.Vue.ref<DialogAgentAssessmentConfig>(cloneDeep(props.assessmentConfig));


/**
 * Whether the sandbox section is visible, not necessarily whether it can be used
 * The sandbox won't be visible if the dialog agent AI feature is disabled
 *
 * NOTE: It's crucial that !== false is used here as sbProps.aiFeatures only gets populated for staff at the moment,
 * but a student could visit this page. If they do, they'll just see the empty state below instead of the sandbox. that's fine for now
 */
const sandboxVisible = sbProps.aiFeatures.dialogagent?.enabled !== false;

/**
 * The sandbox should warn if there are unsaved changes (and hence it's not actually reflecting the current config)
 */
const sandboxDataCache = window.Vue.ref(cloneDeep(assessmentConfigInfo.value));
const isSandboxDirty = window.Vue.computed(() => {
    return JSON.stringify(sandboxDataCache.value) !== JSON.stringify(assessmentConfigInfo.value);
});

type DialogAgentConfigureApiResponse = {
    _routes: {
        postSandboxMessage: string;
    };
};

type DialogAgentConfigureApiRequest = Omit<DialogAgentAssessmentConfig, "fieldContexts" | "_routes"> & {
    fieldContexts: Record<DialogAgentFieldContext["id"], DialogAgentFieldContext["body"]>;
};

const isSubmitting = window.Vue.ref<boolean>(false);
const submitError = window.Vue.ref<string | null>(null);

/**
 * Submit the new configuration
 */
async function onSubmit(): Promise<void> {
    if (!props.postUpdate) {
        throw new Error("unable to submit, no postUpdate route"); // shouldn't get here
    }

    // TODO: validate

    isSubmitting.value = true;
    submitError.value = null;

    const fieldContexts: DialogAgentConfigureApiRequest["fieldContexts"] = {};
    for (const fc of assessmentConfigInfo.value.fieldContexts) {
        fieldContexts[fc.id] = fc.body;
    }

    const response = await apiRequest<DialogAgentConfigureApiResponse, DialogAgentConfigureApiRequest>({
        method: "POST",
        url: props.postUpdate,
        body: {
            helpfulness: assessmentConfigInfo.value.helpfulness,
            language: assessmentConfigInfo.value.language,
            useDifferentiationProfile: assessmentConfigInfo.value.useDifferentiationProfile,
            openingMessage: assessmentConfigInfo.value.openingMessage,
            rules: assessmentConfigInfo.value.rules,
            fieldContexts,
        },
    });

    isSubmitting.value = false;
    if (!response.success) {
        submitError.value = response.message;
        return;
    }

    sandboxDataCache.value._routes.postSandboxMessage = response.data._routes.postSandboxMessage;
    sandboxDataCache.value = cloneDeep(assessmentConfigInfo.value);
}
</script>

<template>
    <div class="row">
        <div class="small-12 island" :class="{ 'large-6': sandboxVisible }">
            <h2 class="subheader">Settings</h2>
            <section>
                <fieldset class="content">
                    <div class="small-12 columns">
                        <h3>{{ scenarioInfo.title }}</h3>
                        <article>{{ scenarioInfo.description }}</article>
                    </div>
                </fieldset>
                <fieldset class="content" :disabled="isSubmitting || undefined">
                    <div class="small-12 large-6 columns">
                        <label for="dac-helpfulness">
                            <span class="required-with-tip">Helpfulness</span>
                            <span
                                data-tooltip
                                class="hint has-tip tip-top"
                                data-width="200"
                                title="Helpfulness setting."
                            >
                                ?
                            </span>
                        </label>
                        <select
                            id="dac-helpfulness"
                            v-model="assessmentConfigInfo.helpfulness"
                            name="helpfulness"
                            required
                            :disabled="!props.postUpdate"
                        >
                            <option v-for="(name, value) in props.helpfulnessOptions" :key="value" :value="value">
                                {{ name }}
                            </option>
                        </select>
                    </div>

                    <div class="small-12 large-6 columns">
                        <label for="dac-language">
                            <span class="required-with-tip">Language</span>
                            <span data-tooltip class="hint has-tip tip-top" data-width="200" title="Language setting.">
                                ?
                            </span>
                        </label>
                        <select
                            id="dac-language"
                            v-model="assessmentConfigInfo.language"
                            name="language"
                            required
                            :disabled="!props.postUpdate"
                        >
                            <option v-for="language in props.languageOptions" :key="language" :value="language">
                                {{ language }}
                            </option>
                        </select>
                    </div>

                    <div class="small-12 columns">
                        <label for="dac-differentiation-profile">
                            Use learner's differentiation profile to provide agent context?
                            <span
                                data-tooltip
                                class="hint has-tip tip-top"
                                data-width="200"
                                title="Enable this to use learner's differentiation profile to provide agent context."
                            >
                                ?
                            </span>
                        </label>
                        <div class="switch">
                            <input
                                id="dac-differentiation-profile"
                                v-model="assessmentConfigInfo.useDifferentiationProfile"
                                name="useDifferentiationProfile"
                                type="checkbox"
                                :disabled="!props.postUpdate"
                            />
                            <label for="dac-differentiation-profile">
                                <span class="icon-approve"></span>
                                <span class="icon-cancel"></span>
                            </label>
                        </div>
                    </div>

                    <div class="small-12 columns">
                        <label for="dac-opening-message" class="required">Opening Message</label>
                        <textarea
                            id="dac-opening-message"
                            v-model="assessmentConfigInfo.openingMessage"
                            name="openingMessage"
                            style="resize: vertical"
                            rows="4"
                            required
                            :disabled="!props.postUpdate"
                        />
                    </div>

                    <div class="small-12 columns">
                        <label for="dac-rules" class="required">Rules</label>
                        <textarea
                            id="dac-rules"
                            v-model="assessmentConfigInfo.rules"
                            name="rules"
                            style="resize: vertical"
                            rows="4"
                            required
                            :disabled="!props.postUpdate"
                        />
                    </div>
                </fieldset>

                <fieldset
                    v-if="assessmentConfigInfo.fieldContexts.length"
                    class="content"
                    :disabled="isSubmitting || undefined"
                >
                    <div
                        v-for="fieldContext in assessmentConfigInfo.fieldContexts"
                        :key="fieldContext.id"
                        class="small-12 columns"
                    >
                        <label :for="`field-${fieldContext.field.id}`" class="required">
                            {{ fieldContext.field.title }}
                        </label>
                        <textarea
                            :id="`fieldcontext-${fieldContext.id}`"
                            v-model="fieldContext.body"
                            :name="`fieldContexts[${fieldContext.id}]`"
                            style="resize: vertical"
                            rows="4"
                            required
                            :disabled="!props.postUpdate"
                        />
                    </div>
                </fieldset>
            </section>

            <div v-if="submitError" class="alert-box alert">
                {{ submitError }}
            </div>

            <ul class="flex-list buttons">
                <li v-if="returnUrl"><a class="button" :href="returnUrl">Return</a></li>
                <li v-if="props.postUpdate"><button class="button submit" @click.prevent="onSubmit">Save</button></li>
            </ul>
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
                    :opening-message="sandboxDataCache.openingMessage"
                    :post-message="sandboxDataCache._routes.postSandboxMessage"
                    :is-dirty="isSandboxDirty"
                />
            </section>
        </div>
    </div>
</template>

<style scoped lang="scss"></style>
