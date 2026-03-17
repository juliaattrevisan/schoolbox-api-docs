<script setup lang="ts">
import type { SkinConfigData } from "../js/types/skinConfigTypes";

defineProps<{
    configs: SkinConfigData;
}>();
</script>

<template>
    <section class="small-12 island" data-test="skin-config-container">
        <div v-for="(subcategories, categoryKey) in configs" :key="categoryKey" class="content">
            <header class="u-margin-bottom-sm">
                <h2 class="beta">{{ categoryKey }}</h2>
            </header>

            <div v-for="subcategory in subcategories" :key="subcategory.name" class="u-margin-bottom-md">
                <h3 class="gamma">{{ subcategory.name }}</h3>

                <ul class="no-bullet">
                    <li v-for="option in subcategory.options" :key="option.id">
                        {{ option.title || option.name }}
                    </li>
                </ul>
            </div>
        </div>
    </section>
</template>
