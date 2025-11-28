<script setup lang="ts">
import { sbProps } from "@js/util/sbx/pageUtils";
import { cloneDeep } from "lodash";
import DacSandbox from "./dac-sandbox.vue";

type DialogAgentScenario = {
    title: string;
    description: string;
};

type DialogAgentAssessmentConfig = {
    helpfulness: string | null;
    language: string | null;
    useDifferentiationProfile: boolean;
    openingMessage: string | null;
    rules: string | null;
    fieldContexts: {
        id: number;
        body: string;
        field: {
            id: number;
            title: string;
        };
    }[];
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
 * Submit the new configuration
 */
async function onSubmit(): Promise<void> {
    // TODO
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
                <fieldset class="content">
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
                            class="required"
                            style="resize: vertical"
                            rows="4"
                            :disabled="!props.postUpdate"
                        />
                    </div>

                    <div class="small-12 columns">
                        <label for="dac-rules" class="required">Rules</label>
                        <textarea
                            id="dac-rules"
                            v-model="assessmentConfigInfo.rules"
                            name="rules"
                            class="required"
                            style="resize: vertical"
                            rows="4"
                            :disabled="!props.postUpdate"
                        />
                    </div>
                </fieldset>

                <fieldset v-if="assessmentConfigInfo.fieldContexts.length" class="content">
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
                            class="required"
                            style="resize: vertical"
                            rows="4"
                            :disabled="!props.postUpdate"
                        />
                    </div>
                </fieldset>
            </section>

            <input v-if="props.postUpdate" type="submit" value="Save" @click.prevent="onSubmit" />
        </div>

        <div v-if="sandboxVisible" class="small-12 large-6 island">
            <h2 class="subheader">Sandbox</h2>
            <div
                v-if="!assessmentConfigInfo._routes.postSandboxMessage || !assessmentConfigInfo.openingMessage"
                class="empty-state"
            >
                <i class="icon-dialog-agent"></i>
                <p>Sandbox will be available once the agent configuration has been saved.</p>
            </div>
            <section v-else class="content">
                <dac-sandbox
                    :opening-message="assessmentConfigInfo.openingMessage"
                    :post-message="assessmentConfigInfo._routes.postSandboxMessage"
                />
            </section>
        </div>
    </div>
</template>

<style scoped lang="scss"></style>
