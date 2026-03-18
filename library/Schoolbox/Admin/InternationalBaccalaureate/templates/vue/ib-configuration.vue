<script setup lang="ts">
/**
 * IB DP Subject Configuration page. This page displays configuration options
 * organized by IB subject groups as tabs.
 */

import type { IBCategory } from "../js/types/ibConfigurationTypes";

interface Props {
    categories: IBCategory[]; // The IB subject group categories to display
}
const props = defineProps<Props>();

// Current category state
const currentCategory = window.Vue.ref<IBCategory>(props.categories[0] || { id: 1, name: "general", title: "General" });
const categoryParamName = "category";

/**
 * Handle category selection, update URL.
 * @param category - The selected IB category/tab
 */
function onCategorySelected(category: IBCategory): void {
    currentCategory.value = category;
    // Update URL without adding to browser history
    const url = new URL(window.location.href);
    url.searchParams.set(categoryParamName, category.name);
    window.history.replaceState(null, "", url.toString());
}

/**
 * Initialize the component, check URL for category parameter.
 */
window.Vue.onMounted((): void => {
    // Check if there's a category in the URL
    const urlParams = new URLSearchParams(window.location.search);
    const categoryParam = urlParams.get(categoryParamName);
    // Update the current category if available
    if (categoryParam && props.categories) {
        const foundCategory = props.categories.find((cat) => cat.name === categoryParam);
        if (foundCategory) {
            currentCategory.value = foundCategory;
        }
    }
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
                <a :data-test="`ib-configuration-tab-${category.name}`" @click="onCategorySelected(category)">
                    {{ category.title }}
                </a>
            </dd>
        </dl>

        <section class="content tabs-content">
            <div class="content active">
                <h2>{{ currentCategory.title }}</h2>
            </div>
        </section>
    </div>
</template>
