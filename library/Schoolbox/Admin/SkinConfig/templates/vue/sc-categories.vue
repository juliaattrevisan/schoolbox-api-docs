<script setup lang="ts">
/**
 * Child component for rendering visible Skin Config categories.
 */

import type { SkinConfigData } from "../js/types/skinConfigTypes";

const props = defineProps<{
    configs: SkinConfigData;
}>();

const emit = defineEmits<{
    "preview-refresh": [];
}>();

const configs = window.Vue.ref(props.configs);

window.Vue.watch(
    () => props.configs,
    (value) => {
        configs.value = value;
    },
);

const activeConfigs = window.Vue.computed(() =>
    configs.value.toggle.enabled ? configs.value.sonar : configs.value.existing,
);

// Temporarily hide roundness and drop shadow until their controls are implemented.
const visibleConfigs = window.Vue.computed(() =>
    Object.fromEntries(
        Object.entries(activeConfigs.value).filter(
            ([categoryKey]) => categoryKey !== "Roundness" && categoryKey !== "Drop Shadow",
        ),
    ),
);

/**
 * Notify the parent that the preview should be refreshed.
 */
function onPreviewRefresh(): void {
    emit("preview-refresh");
}
</script>

<template>
    <template v-for="(subcategories, categoryKey) in visibleConfigs" :key="categoryKey">
        <div class="row">
            <div class="list-item">
                <div class="small-12 island">
                    <h2 class="subheader">{{ categoryKey }}</h2>
                </div>
            </div>
        </div>
        <section>
            <fieldset
                v-for="(subcategory, index) in subcategories"
                :key="subcategory.name || `default-${index}`"
                class="content"
                :data-test="`skin-config-subcategory-${subcategory.name || `default-${index}`}`"
            >
                <div v-if="subcategory.name" class="small-12 columns">
                    <h3>{{ subcategory.name }}</h3>
                </div>
                <div
                    v-for="option in subcategory.options"
                    :key="option.id"
                    :class="[
                        option.type === 'textarea'
                            ? 'small-12 columns'
                            : 'small-12 medium-6 large-4 l-mar-m--bottom columns',
                    ]"
                >
                    <label :id="option.name" :for="`${option.name}-field`" class="inline-block">
                        <span>{{ option.title || option.name }}</span>
                        <span
                            v-if="option.description"
                            data-tooltip
                            class="has-tip tip-top hint"
                            data-allow-html="true"
                            :title="option.description"
                            >?</span
                        >
                    </label>
                    <div v-if="option.type === 'colour'">
                        <input
                            :id="`${option.name}-field`"
                            v-model="option.value"
                            type="text"
                            :name="option.name"
                            data-colour-picker
                            :disabled="!option.editable"
                            @input="onPreviewRefresh"
                            @change="onPreviewRefresh"
                        />
                    </div>
                    <div v-else-if="option.type === 'textarea'">
                        <textarea
                            :id="`${option.name}-field`"
                            v-model="option.value"
                            :name="option.name"
                            rows="8"
                            :disabled="!option.editable"
                            @input="onPreviewRefresh"
                            @change="onPreviewRefresh"
                        />
                    </div>
                </div>
                <template v-if="configs.toggle.enabled">
                    <div v-if="subcategory.name === 'Component'" class="small-12 columns">
                        <h4>Preview</h4>
                    </div>
                    <template v-if="subcategory.name === 'Component'">
                        <div class="small-12 columns">
                            <div class="component-titlebar">
                                <div data-component-titlebar-container="" data-action-count="3" class="actions-small-3">
                                    <div class="list-item">
                                        <h2 title="Class News">
                                            <a
                                                href="#"
                                                class="icon-grip"
                                                style="
                                                    display: none;
                                                    touch-action: none;
                                                    cursor: grab;
                                                    user-select: none;
                                                "
                                            ></a>
                                            <span
                                                id="component-title-3402"
                                                data-test="homepage-component-titlebar-name"
                                                data-test-component-instance-id="3402"
                                                >Class News</span
                                            >
                                        </h2>
                                    </div>
                                    <nav>
                                        <a
                                            style="display: none"
                                            data-component-settings=""
                                            data-target="settings"
                                            data-state="closed"
                                            href="#"
                                            class="icon-settings settings edit"
                                            title="Settings"
                                        ></a>
                                        <a
                                            data-collapser="true"
                                            href="#"
                                            class="icon-up-arrow collapse"
                                            title="Expand/collapse content"
                                        ></a>
                                        <a
                                            style="display: none"
                                            data-component-settings=""
                                            data-target="delete"
                                            data-state="closed"
                                            href="#"
                                            class="icon-close"
                                            title="Close"
                                        ></a>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </template>
                </template>
            </fieldset>
        </section>
    </template>
</template>

<style scoped></style>
