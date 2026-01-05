<script setup lang="ts">
import { apiRequest } from "@js/util/sbx/apiUtils";
import { getPageReturnUrl } from "@js/util/sbx/urlUtils";
import type {
    DialogAgentScenario,
    DialogAgentAssessmentConfig,
    DialogAgentConfigureApiRequest,
    DialogAgentConfigureApiResponse,
} from "./dacTypes";

interface Props {
    scenario: DialogAgentScenario;
    helpfulnessOptions: Record<string, string>;
    languageOptions: string[];
    postUpdate: string | null;
}

const props = defineProps<Props>();
const emit = defineEmits(["saved"]);

const assessmentConfig = defineModel<DialogAgentAssessmentConfig>("assessmentConfig", { required: true });
const isSubmitting = defineModel<boolean>("isSubmitting", { required: true });

const returnUrl = getPageReturnUrl();

const submitError = window.Vue.ref<string | null>(null);

const formEl = window.Vue.useTemplateRef<HTMLFormElement>("formRef");
const validator = window.Vue.ref<ReturnType<ReturnType<typeof window.jQuery>["alaressValidate"]> | null>(null);

/**
 * Submit the new configuration
 */
async function onSubmit(): Promise<void> {
    if (!props.postUpdate) {
        throw new Error("unable to submit, no postUpdate route"); // shouldn't get here
    }

    if (!formEl.value) {
        throw new Error("unable to submit, form element not found");
    }

    if (isSubmitting.value) {
        return; // already submitting, ignore
    }

    /** Create validator and destroy any existing one */
    validator.value?.destroy();
    validator.value = $(formEl.value).alaressValidate({
        submitHandler: (_: unknown, event: SubmitEvent) => {
            // Don't do anything in the submit handler:
            // allow Vue to handle saving.
            event.preventDefault();
        },
    });

    window.jQuery(formEl.value).alaressValidate();

    if (!validator.value.form()) {
        return; // validation failed
    }

    isSubmitting.value = true;
    submitError.value = null;

    const fieldContexts: DialogAgentConfigureApiRequest["fieldContexts"] = {};
    for (const fc of assessmentConfig.value.fieldContexts) {
        fieldContexts[fc.id] = fc.body;
    }

    const response = await apiRequest<DialogAgentConfigureApiResponse, DialogAgentConfigureApiRequest>({
        method: "POST",
        url: props.postUpdate,
        body: {
            helpfulness: assessmentConfig.value.helpfulness,
            language: assessmentConfig.value.language,
            useDifferentiationProfile: assessmentConfig.value.useDifferentiationProfile,
            openingMessage: assessmentConfig.value.openingMessage,
            rules: assessmentConfig.value.rules,
            fieldContexts,
        },
    });

    isSubmitting.value = false;
    if (!response.success) {
        submitError.value = response.message;
        return;
    }

    assessmentConfig.value._routes.postSandboxMessage = response.data._routes.postSandboxMessage;
    emit("saved");
}
</script>

<template>
    <form ref="formRef" @submit.prevent="onSubmit">
        <section>
            <fieldset class="content">
                <div class="small-12 columns">
                    <h3>{{ props.scenario.title }}</h3>
                    <article>{{ props.scenario.description }}</article>
                </div>
            </fieldset>
            <fieldset class="content" :disabled="isSubmitting || undefined">
                <div class="small-12 large-6 columns">
                    <label for="dac-helpfulness" class="required">Helpfulness</label>
                    <select
                        id="dac-helpfulness"
                        v-model="assessmentConfig.helpfulness"
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
                    <label for="dac-language" class="required">Language</label>
                    <select
                        id="dac-language"
                        v-model="assessmentConfig.language"
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
                        Use differentiation profile?
                        <span
                            data-tooltip
                            class="hint has-tip tip-top"
                            data-width="200"
                            title="Enable this to use the learner's differentiation profile to provide agent with more context."
                        >
                            ?
                        </span>
                    </label>
                    <div class="switch">
                        <input
                            id="dac-differentiation-profile"
                            v-model="assessmentConfig.useDifferentiationProfile"
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
            </fieldset>

            <fieldset
                v-if="assessmentConfig.fieldContexts.length"
                class="content"
                :disabled="isSubmitting || undefined"
            >
                <div
                    v-for="fieldContext in assessmentConfig.fieldContexts"
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

            <fieldset class="content" :disabled="isSubmitting || undefined">
                <div class="small-12 columns">
                    <label for="dac-opening-message" class="required">Opening Message</label>
                    <p class="meta">Used by the AI to start the conversation and frame the learning discussion.</p>
                    <textarea
                        id="dac-opening-message"
                        v-model="assessmentConfig.openingMessage"
                        name="openingMessage"
                        style="resize: vertical"
                        rows="4"
                        required
                        :disabled="!props.postUpdate"
                    />
                </div>

                <div class="small-12 columns">
                    <label for="dac-rules" class="required">Rules</label>
                    <p class="meta">
                        Sets ongoing guidelines the AI follows to maintain focus, tone, and boundaries during the
                        conversation.
                    </p>
                    <textarea
                        id="dac-rules"
                        v-model="assessmentConfig.rules"
                        name="rules"
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
            <li v-if="props.postUpdate">
                <button class="button submit" type="submit" @click.prevent="onSubmit">Save</button>
            </li>
        </ul>
    </form>
</template>

<style scoped></style>
