<script setup lang="ts">
/**
 * IB DP Subject Configuration page. This page displays configuration options
 * organized by IB subject groups as tabs. Each category can have specific
 * configuration options that are loaded dynamically.
 */

import { kebabToTitleCase } from "@js/util/stringUtils";
import { isAbortError, hasErrorMessage } from "@js/util/errorUtils";
import { extractErrorMessage } from "@js/util/sbx/fetchUtils";
import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import type { IBCategory, IBConfiguration } from "../js/types/ibConfigurationTypes";

interface Props {
    categories: IBCategory[]; // The IB subject group categories to display
    configurationEndpoint: string; // The endpoint to fetch configuration data from
    saveEndpoint: string; // The endpoint to save configuration data to
}
const props = defineProps<Props>();

// Current category state
const currentCategory = window.Vue.ref<IBCategory>(props.categories[0]);
const isLoading = window.Vue.ref<boolean>(false);
const loadError = window.Vue.ref<string | null>(null);
const currentConfigurations = window.Vue.ref<IBConfiguration[]>([]);
const categoryParamName = "category";
const isSaving = window.Vue.ref<boolean>(false);
const saveError = window.Vue.ref<string | null>(null);

/**
 * Load configuration data for the current category from the API.
 */
async function loadCategoryConfigurations(): Promise<void> {
    if (!props.configurationEndpoint) {
        return;
    }
    
    try {
        isLoading.value = true;
        loadError.value = null;
        
        const url = props.configurationEndpoint.replace("{categoryId}", currentCategory.value.id.toString());
        console.log("Fetching configuration from URL:", url);
        const response = await fetch(url, {
            headers: {
                Accept: "application/json",
            },
        });
        
        if (!response.ok) {
            const errorMessage = await extractErrorMessage(response);
            throw new Error(errorMessage);
        }
        
        const responseJson = (await response.json()) as IBConfiguration[];
        if (responseJson === null || !Array.isArray(responseJson)) {
            throw new Error("Unexpected response format");
        }
        
        currentConfigurations.value = responseJson;
        
    } catch (error) {
        if (isAbortError(error)) {
            return;
        }
        if (error instanceof Error && hasErrorMessage(error)) {
            loadError.value = error.message;
        }
        console.error(error);
    } finally {
        isLoading.value = false;
    }
}

/**
 * Save a configuration value
 */
async function saveConfiguration(config: IBConfiguration, newValue: string): Promise<void> {
    if (!props.saveEndpoint) {
        return;
    }
    
    try {
        isSaving.value = true;
        saveError.value = null;
        
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
                categoryId: config.categoryId
            }),
        });
        
        if (!response.ok) {
            const errorMessage = await extractErrorMessage(response);
            throw new Error(errorMessage);
        }
        
        // Update the local state
        config.value = newValue;
        
        // Show success message
        const toast = window.sbProps.toastActivate("Configuration saved successfully");
        toast.timeout({ text: "Saved" });
        
    } catch (error) {
        if (error instanceof Error && hasErrorMessage(error)) {
            saveError.value = error.message;
        }
        const toast = window.sbProps.toastActivate("Save failed");
        toast.timeout({ text: "Save failed", isSuccess: false });
        console.error(error);
    } finally {
        isSaving.value = false;
    }
}

/**
 * Handle category selection, update URL and load configurations.
 */
function onCategorySelected(category: IBCategory): void {
    currentCategory.value = category;
    // Update URL without adding to browser history
    const url = new URL(window.location.href);
    url.searchParams.set(categoryParamName, category.name);
    window.history.replaceState(null, "", url.toString());
    void loadCategoryConfigurations();
}

/**
 * Initialize the component, check URL for category parameter.
 */
window.Vue.onMounted((): void => {
    // Check if there's a category in the URL
    const urlParams = new URLSearchParams(window.location.search);
    const categoryParam = urlParams.get(categoryParamName);
    // Update the current category if available
    if (categoryParam) {
        const foundCategory = props.categories.find((cat) => cat.name === categoryParam);
        if (foundCategory) {
            currentCategory.value = foundCategory;
        }
    }
    void loadCategoryConfigurations();
});
</script>

<template>
    <div class="small-12 island">
        <dl ref="tabsEl" class="tabs" data-test="ib-configuration-tabs">
            <dd
                v-for="category in props.categories"
                :key="category.id"
                :class="{ active: currentCategory.name === category.name }"
            >
                <a
                    :data-test="`ib-configuration-tab-${category.name}`"
                    @click="onCategorySelected(category)"
                >
                    {{ category.title }}
                </a>
            </dd>
        </dl>

        <loading-table v-if="isLoading" />

        <section v-else-if="loadError" class="content">
            <data-load-error :data-title="currentCategory.title" :further-info="loadError" />
        </section>

        <section v-else class="content tabs-content">
            <div class="content active">
                <h2>{{ currentCategory.title }}</h2>

                <div v-if="currentConfigurations.length === 0" class="alert alert-info">
                    <i class="fa fa-info-circle"></i>
                    No configurations available for {{ currentCategory.title }}.
                </div>

                <form @submit.prevent="void 0" class="c-island">
                    <div v-for="config in currentConfigurations" :key="config.id" class="form-row">
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
                                :value="config.value"
                                :data-test="`ib-configuration-input-${config.key}`"
                                class="form-control"
                                @blur="(event) => saveConfiguration(config, (event.target as HTMLInputElement).value)"
                                :disabled="isSaving"
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
                </form>
            </div>
        </section>
    </div>
</template>

<style scoped>
.form-row {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: bold;
}

.tooltip-icon {
    margin-left: 0.5rem;
    color: #666;
    cursor: help;
}

.tooltip-icon:hover {
    color: #333;
}

.form-field {
    position: relative;
}

.form-control {
    width: 100%;
    max-width: 400px;
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
}

.form-control:focus {
    outline: none;
    border-color: #007cba;
    box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.2);
}

.form-control:disabled {
    background-color: #f5f5f5;
    cursor: not-allowed;
}

.form-help {
    display: block;
    margin-top: 0.25rem;
    color: #666;
    font-size: 0.875rem;
    line-height: 1.4;
}

.alert-box {
    margin-top: 1rem;
    padding: 0.75rem 1rem;
    border-radius: 4px;
}

.alert-box.alert {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
</style>
