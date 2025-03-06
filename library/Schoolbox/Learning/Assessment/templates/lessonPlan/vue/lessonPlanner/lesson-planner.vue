<script setup lang="ts">
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";

/**
 * An ordered list of each field of the lesson plan
 */
const fields = window.Vue.ref([
    {
        id: "new1",
        label: "Learning Intention",
        meta: null,
    },
    {
        id: "new2",
        label: "Success Criteria",
        meta: "Some meta about this field",
    },
    {
        id: "new3",
        label: "Learning Sequence",
        meta: "Some meta about this field",
    },
    {
        id: "new4",
        label: "Differentiation",
        meta: "Some meta about this field",
    },
    {
        id: "new5",
        label: "Resources",
        meta: null,
    },
    {
        id: "new6",
        label: "Teacher Notes",
        meta: null,
    },
]);

const isModalOpen = window.Vue.ref(false);

/**
 * Open the modal to the initial page
 */
function openModal(): void {
    isModalOpen.value = true;
}
</script>

<template>
    <button type="button" class="button expand icon-chat" @click="openModal">Generate lesson with AI</button>

    <basic-modal v-model:is-open="isModalOpen" :has-background-click="true">
        <section>
            <label for="lp-generate-fields">
                Select the sections of your lesson plan which you want AI to help with:
            </label>
            <ul id="lp-generate-fields" class="option-list">
                <li v-for="field in fields" :key="field.id">
                    <input :id="`lp-generate-field-${field.id}`" type="checkbox" />
                    <label :for="`lp-generate-field-${field.id}`" class="checkbox-label">
                        {{ field.label }}
                        <template v-if="field.meta">
                            <br />
                            <span class="meta">{{ field.meta }}</span>
                        </template>
                    </label>
                </li>
            </ul>

            <label for="lp-generate-additional-context">Additional Context</label>
            <textarea id="lp-generate-additional-context" rows="5"></textarea>

            <button type="button" class="button expand icon-chat">Generate lesson with AI</button>
        </section>
    </basic-modal>
</template>

<style scoped></style>
