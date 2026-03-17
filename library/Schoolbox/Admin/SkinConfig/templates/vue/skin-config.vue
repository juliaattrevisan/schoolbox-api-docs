<script setup lang="ts">
import type { SkinConfigData } from "../js/types/skinConfigTypes";

const props = defineProps<{
    configs: SkinConfigData;
}>();

const configs = window.Vue.ref<SkinConfigData>(props.configs);
const isProcessing = window.Vue.ref(false);
const saveText = window.Vue.computed(() => (isProcessing.value ? "Saving..." : "Save"));

/**
 * Initialise colour picker inputs once the component has rendered.
 */
async function initialiseColourPickers(): Promise<void> {
    await window.Vue.nextTick();

    document.querySelectorAll("[data-colour-picker]").forEach((element) => {
        if (!(element instanceof HTMLInputElement) || element.id === "") {
            return;
        }

        document.dispatchEvent(
            new CustomEvent("colourPickerInitialise", {
                detail: {
                    el: `#${element.id}`,
                    format: "rgb",
                },
            }),
        );
    });
}

/**
 * Placeholder submit handler for the initial Skin Config app scaffold.
 */
function onSaveSelected(): void {
    if (isProcessing.value) {
        return;
    }
}

window.Vue.onMounted(() => {
    void initialiseColourPickers();
});
</script>

<template>
    <div data-test="skin-config-container">
        <form method="post" @submit.prevent="onSaveSelected">
            <div
                v-for="(subcategories, categoryKey) in configs"
                :key="categoryKey"
                class="small-12 island"
                :data-test="`skin-config-category-${categoryKey}`"
            >
                <div class="row">
                    <div class="list-item">
                        <div class="small-12 island">
                            <h2 class="subheader">{{ categoryKey }}</h2>
                        </div>
                    </div>
                </div>
                <section>
                    <fieldset
                        v-for="subcategory in subcategories"
                        :key="subcategory.name"
                        class="content"
                        :data-test="`skin-config-subcategory-${subcategory.name}`"
                    >
                        <div class="small-12 columns">
                            <h3>{{ subcategory.name }}</h3>
                        </div>
                        <div
                            v-for="option in subcategory.options"
                            :key="option.id"
                            class="small-12 medium-6 large-3 columns"
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
                            <div>
                                <input
                                    :id="`${option.name}-field`"
                                    v-model="option.value"
                                    type="text"
                                    :name="option.name"
                                    data-colour-picker
                                    :disabled="!option.editable"
                                />
                            </div>
                        </div>
                    </fieldset>
                </section>
            </div>
            <div class="small-12 island">
                <div class="row">
                    <div class="small-12 column">
                        <input type="submit" name="btnSubmit" :value="saveText" />
                    </div>
                </div>
            </div>
        </form>
    </div>
</template>
