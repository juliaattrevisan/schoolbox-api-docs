<script setup lang="ts">
import type { IBConfiguration } from "../../js/types/ibConfigurationTypes";

interface Props {
    configurations: IBConfiguration[];
    isLoading: boolean;
    saveError: string | null;
    saveEndpoint: string;
    categoryTitle: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    configChange: [config: IBConfiguration, newValue: string];
}>();

const isSaving = window.Vue.ref<boolean>(false);
const currentValues = window.Vue.ref<{ [key: string]: string }>({});

// Initialize form values
window.Vue.watch(
    () => props.configurations,
    (newConfigs) => {
        newConfigs.forEach((config) => {
            currentValues.value[config.key] = config.value || "";
        });
    },
    { immediate: true, deep: true },
);

/**
 * Handles input changes for configuration fields
 * @param config - The configuration object that changed
 * @param newValue - The new value for the configuration
 */
function onInputChange(config: IBConfiguration, newValue: string): void {
    currentValues.value[config.key] = newValue;
    emit("configChange", config, newValue);
}

/**
 * Saves the general configuration to the server
 */
async function saveGeneralConfig(): Promise<void> {
    if (!props.saveEndpoint) return;

    try {
        isSaving.value = true;

        for (const config of props.configurations) {
            const newValue = currentValues.value[config.key];

            const response = await fetch(props.saveEndpoint, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                },
                body: JSON.stringify({
                    id: config.id,
                    key: config.key,
                    value: newValue,
                    categoryId: config.categoryId,
                }),
            });

            if (!response.ok) {
                throw new Error("Save failed");
            }
        }

        const toast = window.sbProps.toastActivate(`${props.categoryTitle} configuration saved successfully`);
        toast.timeout({ text: "Saved" });
    } catch (error) {
        const toast = window.sbProps.toastActivate("Save failed");
        toast.timeout({ text: "Save failed", isSuccess: false });
        console.error(error);
    } finally {
        isSaving.value = false;
    }
}
</script>

<!--
<template>
    <div class="general-config">
        <div v-if="configurations.length === 0" class="alert alert-info">
            <i class="fa fa-info-circle"></i>
            No configurations available for {{ categoryTitle }}.
        </div>

        <form v-else class="c-island" @submit.prevent="void 0">
            <div v-for="config in configurations" :key="config.id" class="form-row">
                <label :for="`config-${config.key}`" class="form-label">
                    {{ config.label }}
                    <span v-if="config.description" :title="config.description" class="tooltip-icon">
                        <i class="fa fa-question-circle"></i>
                    </span>
                </label>
                <div class="form-field">
                    <input
                        :id="`config-${config.key}`"
                        :type="config.type"
                        :value="currentValues[config.key] || config.value"
                        :data-test="`ib-configuration-input-${config.key}`"
                        class="form-control"
                        :disabled="isSaving"
                        @input="(event) => onInputChange(config, (event.target as HTMLInputElement).value)"
                    />
                    <small v-if="config.description" class="form-help">
                        {{ config.description }}
                    </small>
                </div>
            </div>

            <div v-if="saveError" class="alert alert-box alert">
                <i class="fa fa-exclamation-triangle"></i>
                {{ saveError }}
            </div>

            <div class="form-actions">
                <button type="button" class="button submit" :disabled="isSaving" @click="saveGeneralConfig">
                    <i v-if="isSaving" class="fa fa-spinner fa-spin"></i>
                    {{ isSaving ? "Saving..." : "Save General Configuration" }}
                </button>
            </div>
        </form>
    </div>
</template>
-->
