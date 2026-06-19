<script setup lang="ts">
import { cloneDeep } from "es-toolkit";
import locale from "@js/terminology/rosettaStone";
import { apiRequest } from "@js/util/sbx/apiUtils";
import { getPageReturnUrl } from "@js/util/sbx/urlUtils";
import { EditorToolbar } from "@js/editor/types/editorTypes";
import TextEditor from "@schoolboxVue/components/ckeditor/text-editor.vue";
import type {
    DialogAgentScenario,
    DialogAgentAssessmentConfig,
    DialogAgentConfigureApiRequest,
    DialogAgentConfigureApiResponse,
    DialogAgentHelpfulness,
} from "./dacTypes";

interface Props {
    scenario: DialogAgentScenario;
    helpfulnessOptions: { value: DialogAgentHelpfulness; label: string }[];
    languageOptions: string[];
    differentiationProfilesEnabled: boolean;
    postUpdate: string | null;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (event: "saved", data: DialogAgentConfigureApiResponse): void;
}>();

const assessmentConfig = defineModel<DialogAgentAssessmentConfig>("assessmentConfig", { required: true });
const isSubmitting = defineModel<boolean>("isSubmitting", { required: true });

const returnUrl = getPageReturnUrl();

const submitError = window.Vue.ref<string | null>(null);

const formEl = window.Vue.useTemplateRef<HTMLFormElement>("formRef");
const validator = window.Vue.ref<ReturnType<ReturnType<typeof $>["alaressValidate"]> | null>(null);

/**
 * The sandbox should warn if there are unsaved changes (and hence it's not actually reflecting the current config)
 */
const assessmentConfigCache = window.Vue.ref(cloneDeep(assessmentConfig.value));
const isAssessmentConfigDirty = window.Vue.computed(() => {
    return JSON.stringify(assessmentConfigCache.value) !== JSON.stringify(assessmentConfig.value);
});

defineExpose({ isAssessmentConfigDirty });

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
    validator.value = window.jQuery(formEl.value).alaressValidate({
        submitHandler: (_: unknown, event: SubmitEvent) => {
            // Don't do anything in the submit handler:
            // allow Vue to handle saving.
            event.preventDefault();
        },
    });

    $(formEl.value).alaressValidate();

    if (!validator.value.form()) {
        return; // validation failed
    }

    isSubmitting.value = true;
    submitError.value = null;

    const fieldContexts: DialogAgentConfigureApiRequest["fieldContexts"] = {};
    for (const fc of assessmentConfig.value.fieldContexts) {
        fieldContexts[fc.id] = fc.body;
    }

    const toast = window.sbProps.toastActivate("Saving...");
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
        toast.timeout({ text: response.message, isSuccess: false });
        return;
    }

    toast.timeout({ text: "Configuration saved", isSuccess: true });

    /**
     * Update the assessmentConfig with any returned data that changed
     * Notably don't update openingMessage, only the sandbox needs the HTML version
     */
    assessmentConfig.value.prompt = response.data.prompt;
    assessmentConfig.value._routes.postSandboxMessage = response.data._routes.postSandboxMessage;

    assessmentConfigCache.value = cloneDeep(assessmentConfig.value);

    emit("saved", response.data);
}

window.Vue.onMounted(() => {
    if (document.readyState === "complete") {
        // If the document isn't ready, we can't reflow tooltips yet
        // As vue loads the template after foundation has been initialised, we need to reinitialise the tooltips
        window.jQuery(document).foundation("tooltip", "reflow");
    }
});
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
                    <label for="dac-helpfulness">
                        <span class="required-with-tip">Helpfulness</span>
                        <span
                            class="hint has-tip tip-top"
                            data-tooltip
                            title="Controls how much direction the AI provides to the learner.
<br>
<br><strong>Exploratory</strong>: encourages independent thinking with minimal guidance.
<br><strong>Guided</strong>: supports learning through structured prompts and guiding questions.
<br><strong>Directed</strong>: provides clear instructions and explicit guidance when learners need stronger support."
                        >
                            ?
                        </span>
                    </label>
                    <select
                        id="dac-helpfulness"
                        v-model="assessmentConfig.helpfulness"
                        name="helpfulness"
                        required
                        :disabled="!props.postUpdate"
                        data-test="dac-form-helpfulness"
                    >
                        <option v-for="option in props.helpfulnessOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
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
                        data-test="dac-form-language"
                    >
                        <option v-for="language in props.languageOptions" :key="language" :value="language">
                            {{ language }}
                        </option>
                    </select>
                </div>

                <div v-if="differentiationProfilesEnabled" class="small-12 columns">
                    <label for="dac-differentiation-profile">
                        Use {{ locale.differentiationProfile.singular() }}?
                        <span
                            data-tooltip
                            class="hint has-tip tip-top"
                            data-width="200"
                            :title="`If enabled, the AI agent will be able to access a student's ${locale.differentiationProfile.proper()} to inform its responses.`"
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
                            <span><sbx-icon name="approve" size="m" color="currentColor"></sbx-icon></span>
                            <span><sbx-icon name="close" size="m" color="currentColor"></sbx-icon></span>
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
                    v-for="(fieldContext, index) in assessmentConfig.fieldContexts"
                    :key="fieldContext.id"
                    class="small-12 columns"
                >
                    <label :for="`fieldContext-${fieldContext.id}`" class="required">
                        {{ fieldContext.field.title }}
                    </label>
                    <p v-if="fieldContext.field.meta" class="meta">
                        {{ fieldContext.field.meta }}
                    </p>
                    <text-editor
                        :id="`fieldContext-${fieldContext.id}`"
                        v-model:body="fieldContext.body"
                        required
                        :config="{
                            markdown: true,
                            toolbar: EditorToolbar.SIMPLE,
                        }"
                        :data-test="`dac-form-field-${fieldContext.id}`"
                        @editor-loaded="
                            // When an editor loads for the first time, it will mutate the saved value for that field from markdown to HTML.
                            // This shouldn't count as a cache invalidation, so treat the first update of each field as the new value in the cache
                            () =>
                                (assessmentConfigCache.fieldContexts[index].body =
                                    assessmentConfig.fieldContexts[index].body)
                        "
                    />
                </div>
            </fieldset>

            <fieldset class="content" :disabled="isSubmitting || undefined">
                <div class="small-12 columns">
                    <label for="dac-opening-message" class="required">Opening Message</label>
                    <p class="meta">Used by the AI to start the conversation and frame the learning discussion.</p>
                    <text-editor
                        id="dac-opening-message"
                        v-model:body="assessmentConfig.openingMessage"
                        required
                        :config="{
                            markdown: true,
                            toolbar: EditorToolbar.SIMPLE,
                        }"
                        data-test="dac-form-opening-message"
                        @editor-loaded="
                            // When an editor loads for the first time, it will mutate the saved value for that field from markdown to HTML.
                            // This shouldn't count as a cache invalidation, so treat the first update of each field as the new value in the cache
                            () => (assessmentConfigCache.openingMessage = assessmentConfig.openingMessage)
                        "
                    />
                </div>

                <div class="small-12 columns">
                    <label for="dac-rules" class="required">Rules</label>
                    <p class="meta">
                        Sets ongoing guidelines the AI follows to maintain focus, tone, and boundaries during the
                        conversation.
                    </p>
                    <text-editor
                        id="dac-rules"
                        v-model:body="assessmentConfig.rules"
                        required
                        :config="{
                            markdown: true,
                            toolbar: EditorToolbar.SIMPLE,
                        }"
                        data-test="dac-form-rules"
                        @editor-loaded="
                            // When an editor loads for the first time, it will mutate the saved value for that field from markdown to HTML.
                            // This shouldn't count as a cache invalidation, so treat the first update of each field as the new value in the cache
                            () => (assessmentConfigCache.rules = assessmentConfig.rules)
                        "
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
                <button class="button submit" type="submit" data-test="dac-form-save" @click.prevent="onSubmit">
                    Save
                </button>
            </li>
        </ul>
    </form>
</template>

<style scoped></style>
