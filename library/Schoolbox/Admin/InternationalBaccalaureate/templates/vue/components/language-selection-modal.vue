<script setup lang="ts">
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";

interface Props {
    selectedLanguages: string[];
}
const props = defineProps<Props>();

const emit = defineEmits<{
    close: [];
    languagesSelected: [languages: string[]];
}>();

const isOpen = defineModel<boolean>("is-open", { default: false });

const availableLanguages: Array<{ code: string; name: string }> = [
    { code: "en", name: "English" },
    { code: "es", name: "Spanish" },
    { code: "fr", name: "French" },
    { code: "de", name: "German" },
    { code: "it", name: "Italian" },
    { code: "pt", name: "Portuguese" },
    { code: "ru", name: "Russian" },
    { code: "zh", name: "Chinese" },
    { code: "ja", name: "Japanese" },
    { code: "ko", name: "Korean" },
    { code: "ar", name: "Arabic" },
    { code: "hi", name: "Hindi" },
    { code: "nl", name: "Dutch" },
    { code: "sv", name: "Swedish" },
    { code: "no", name: "Norwegian" },
    { code: "da", name: "Danish" },
    { code: "fi", name: "Finnish" },
    { code: "pl", name: "Polish" },
    { code: "cs", name: "Czech" },
    { code: "hu", name: "Hungarian" },
];

const localSelectedLanguages = window.Vue.ref<string[]>([...props.selectedLanguages]);

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
                <li v-for="lang in availableLanguages" :key="lang.code">
                    <input
                        :id="`lang-${lang.code}`"
                        v-model="localSelectedLanguages"
                        type="checkbox"
                        :value="lang.code"
                    />
                    <label :for="`lang-${lang.code}`">{{ lang.name }}</label>
                </li>
            </ul>
            <div class="l-flex l-gap-xs f-no-margins">
                <button class="button l-flex__item--1" @click.prevent="isOpen = false">Cancel</button>
                <button class="button submit l-flex__item--1" @click.prevent="onConfirm">Confirm</button>
            </div>
        </div>
    </BasicModal>
</template>
