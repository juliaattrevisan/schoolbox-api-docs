<script setup lang="ts">
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import type { IBLanguage } from "../../js/types/ibConfigurationTypes";

interface Props {
    availableLanguages: IBLanguage[];
    selectedLanguages: number[];
}
const props = defineProps<Props>();

const emit = defineEmits<{
    close: [];
    languagesSelected: [languages: number[]];
}>();

const isOpen = defineModel<boolean>("is-open", { default: false });

const localSelectedLanguages = window.Vue.ref<number[]>([...props.selectedLanguages]);

// Reinitialize selection when modal opens so it reflects the correct row's languages
window.Vue.watch(isOpen, (newVal) => {
    if (newVal) {
        localSelectedLanguages.value = [...props.selectedLanguages];
    } else {
        emit("close");
    }
});

/**
 * Confirms the language selection, emits the selected languages, and closes the modal.
 */
function onConfirm(): void {
    emit("languagesSelected", [...localSelectedLanguages.value]);
    isOpen.value = false;
}
</script>

<template>
    <BasicModal v-model:is-open="isOpen" :has-background-click="true" size="s">
        <div class="l-flex-col l-gap-s f-no-margins checklist">
            <h2>Select Languages</h2>
            <ul class="option-list">
                <li v-for="lang in props.availableLanguages" :key="lang.id">
                    <input :id="`lang-${lang.id}`" v-model="localSelectedLanguages" type="checkbox" :value="lang.id" />
                    <label :for="`lang-${lang.id}`">{{ lang.name }}</label>
                </li>
            </ul>
            <div class="l-flex l-gap-xs f-no-margins">
                <button class="button l-flex__item--1" @click.prevent="isOpen = false">Cancel</button>
                <button class="button submit l-flex__item--1" @click.prevent="onConfirm">Confirm</button>
            </div>
        </div>
    </BasicModal>
</template>
