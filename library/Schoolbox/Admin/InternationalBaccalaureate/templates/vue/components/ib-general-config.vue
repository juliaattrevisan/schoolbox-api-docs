<script setup lang="ts">
import { apiRequest } from "@js/util/sbx/apiUtils";
import type { IBConfiguration } from "../../js/types/ibConfigurationTypes";

interface Props {
    configurations: IBConfiguration[];
    isLoading: boolean;
    saveError: string | null;
    saveEndpoint: string;
    categoryTitle: string;
}
const props = defineProps<Props>();

const isSaving = window.Vue.ref<boolean>(false);
const currentValues = window.Vue.ref<Record<string, string>>(
    Object.fromEntries(props.configurations.map((config) => [config.key, config.value || ""])),
);

/**
 * Saves the general configuration to the server
 */
async function saveGeneralConfig(): Promise<void> {
    if (!props.saveEndpoint) return;

    isSaving.value = true;
    const toast = window.sbProps.toastActivate("Saving...");

    for (const config of props.configurations) {
        const response = await apiRequest<{ success: boolean }, Record<string, unknown>>({
            url: props.saveEndpoint,
            method: "POST",
            body: {
                id: config.id,
                key: config.key,
                value: currentValues.value[config.key] ?? config.value,
                categoryId: config.categoryId,
            },
        });

        if (!response.success) {
            isSaving.value = false;
            toast.timeout({ text: response.message || "Save failed", isSuccess: false });
            return;
        }
    }

    isSaving.value = false;
    toast.timeout({ text: `${props.categoryTitle} configuration saved successfully` });
}
</script>

<template>
    <div v-if="configurations.length === 0" class="alert alert-info">
        <i class="fa fa-info-circle"></i>
        No configurations available for {{ categoryTitle }}.
    </div>

    <form v-else @submit.prevent="saveGeneralConfig">
        <section>
            <fieldset class="content">
                <div v-for="config in configurations" :key="config.id" class="small-12 columns">
                    <label :for="`config-${config.key}`">
                        {{ config.label }}
                        <span v-if="config.description" :title="config.description" class="hint has-tip tip-top"
                            >?</span
                        >
                    </label>
                    <input
                        :id="`config-${config.key}`"
                        v-model="currentValues[config.key]"
                        :type="config.type"
                        :data-test="`ib-configuration-input-${config.key}`"
                        :disabled="isSaving"
                    />
                </div>

                <div v-if="saveError" class="alert-box alert small-12 columns">
                    <i class="fa fa-exclamation-triangle"></i>
                    {{ saveError }}
                </div>

                <div class="small-12 columns">
                    <button type="submit" class="button submit" :disabled="isSaving">
                        <div v-if="isSaving" class="loader"></div>
                        {{ isSaving ? "Saving..." : "Save General Configuration" }}
                    </button>
                </div>
            </fieldset>
        </section>
    </form>
</template>
