<script setup lang="ts">
import type { SkinConfigData } from "../js/types/skinConfigTypes";

const isProcessing = window.Vue.ref(false);
const saveText = window.Vue.computed(() => (isProcessing.value ? "Saving..." : "Save"));

/**
 * Placeholder submit handler for the initial Skin Config app scaffold.
 */
function onSaveSelected(): void {
    if (isProcessing.value) {
        return;
    }
}

defineProps<{
    configs: SkinConfigData;
}>();
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
                            <h3 class="gamma">{{ subcategory.name }}</h3>
                        </div>

                        <div v-for="option in subcategory.options" :key="option.id" class="small-12 columns">
                            {{ option.title || option.name }}
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
