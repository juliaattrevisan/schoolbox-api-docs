<script setup lang="ts">
import RevealModal from "@schoolboxVue/components/reveal-modal.vue";

interface Language {
    code: string;
    name: string;
}

interface Props {
    selectedLanguages: string[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
    close: [];
    languagesSelected: [languages: string[]];
}>();

const modal = window.Vue.ref<InstanceType<typeof RevealModal> | null>(null);

// Available languages - this could come from an API in the future
const availableLanguages: Language[] = [
    { code: 'en', name: 'English' },
    { code: 'es', name: 'Spanish' },
    { code: 'fr', name: 'French' },
    { code: 'de', name: 'German' },
    { code: 'it', name: 'Italian' },
    { code: 'pt', name: 'Portuguese' },
    { code: 'ru', name: 'Russian' },
    { code: 'zh', name: 'Chinese' },
    { code: 'ja', name: 'Japanese' },
    { code: 'ko', name: 'Korean' },
    { code: 'ar', name: 'Arabic' },
    { code: 'hi', name: 'Hindi' },
    { code: 'nl', name: 'Dutch' },
    { code: 'sv', name: 'Swedish' },
    { code: 'no', name: 'Norwegian' },
    { code: 'da', name: 'Danish' },
    { code: 'fi', name: 'Finnish' },
    { code: 'pl', name: 'Polish' },
    { code: 'cs', name: 'Czech' },
    { code: 'hu', name: 'Hungarian' },
];

const tempSelectedLanguages = window.Vue.ref<string[]>([]);

// Watch for prop changes to update temp selection
window.Vue.watch(() => props.selectedLanguages, (newSelection) => {
    tempSelectedLanguages.value = [...newSelection];
}, { immediate: true });

function toggleLanguage(languageCode: string): void {
    const index = tempSelectedLanguages.value.indexOf(languageCode);
    if (index > -1) {
        tempSelectedLanguages.value.splice(index, 1);
    } else {
        tempSelectedLanguages.value.push(languageCode);
    }
}

function open(): void {
    if (modal.value) {
        modal.value.open();
    }
}

function selectLanguages(): void {
    emit('languagesSelected', [...tempSelectedLanguages.value]);
    closeModal();
}

function closeModal(): void {
    // Reset temp selection to original
    tempSelectedLanguages.value = [...props.selectedLanguages];
    if (modal.value) {
        modal.value.close();
    }
    emit('close');
}

function getLanguageName(code: string): string {
    return availableLanguages.find(lang => lang.code === code)?.name || code;
}

// Expose the open method
defineExpose({ open });
</script>

<template>
    <RevealModal ref="modal" size="medium" @closed="closeModal">
        <div class="language-modal-content">
            <div class="language-modal-header">
                <h3>Select Languages</h3>
            </div>
            
            <div class="language-modal-body">
                <div class="language-list">
                    <div 
                        v-for="language in availableLanguages" 
                        :key="language.code" 
                        class="language-item"
                    >
                        <label class="language-label">
                            <input
                                type="checkbox"
                                :value="language.code"
                                :checked="tempSelectedLanguages.includes(language.code)"
                                @change="toggleLanguage(language.code)"
                            />
                            <span class="language-name">{{ language.name }}</span>
                        </label>
                    </div>
                </div>
                
                <div v-if="tempSelectedLanguages.length > 0" class="selected-summary">
                    Selected: {{ tempSelectedLanguages.map(getLanguageName).join(', ') }}
                </div>
            </div>
            
            <div class="language-modal-footer">
                <button type="button" class="button" @click="closeModal">
                    Cancel
                </button>
                <button 
                    type="button" 
                    class="button submit" 
                    :disabled="tempSelectedLanguages.length === 0"
                    @click="selectLanguages"
                >
                    Select {{ tempSelectedLanguages.length }} Language(s)
                </button>
            </div>
        </div>
    </RevealModal>
</template>