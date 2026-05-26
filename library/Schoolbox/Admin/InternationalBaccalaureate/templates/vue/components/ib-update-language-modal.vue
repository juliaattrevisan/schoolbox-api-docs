<script setup lang="ts">
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import RequestError from "@schoolboxVue/components/error/request-error.vue";
import { useIbReportUpdateLanguageStore } from "../store/ibReportUpdateLanguageStore";

const updateLanguageStore = useIbReportUpdateLanguageStore();
</script>

<template>
    <basic-modal v-model:is-open="updateLanguageStore.isOpen">
        <form
            v-if="updateLanguageStore.activeStudent"
            class="l-flex-col l-gap-m f-no-margins"
            @submit.prevent="updateLanguageStore.save"
        >
            <h2>Update Language Selection</h2>
            <p class="f-no-margins">Manually assigning languages for: {{ updateLanguageStore.activeStudent.name }}</p>

            <div class="c-vert-scroll-container c-vert-scroll-container--with-border l-flex-col l-gap-s">
                <div v-for="(row, index) in updateLanguageStore.subjectRows" :key="index">
                    <search-select
                        v-model:selected="row.selected"
                        :label="`${row.level}: ${row.subject}`"
                        :label-id="`ib-update-lang-${index}`"
                        :endpoint="updateLanguageStore.searchLanguagesEndpoint"
                        :default-results="row.defaultResults"
                        :is-single-select="true"
                        :has-error="row.hasError"
                        error-message="Please select a language"
                        placeholder="Search for a language..."
                    />
                </div>
            </div>

            <request-error v-if="updateLanguageStore.saveError" :further-info="updateLanguageStore.saveError" />

            <div class="l-flex l-gap-xs f-no-margins">
                <button type="button" class="button l-flex__item--1" @click.prevent="updateLanguageStore.close()">
                    Cancel
                </button>
                <button class="button submit l-flex__item--1" :disabled="updateLanguageStore.isSaving">
                    {{ updateLanguageStore.isSaving ? "Saving..." : "Save" }}
                </button>
            </div>
        </form>
    </basic-modal>
</template>
