<script setup lang="ts">
/**
 * To provide another way to save the score and comments
 */

import type { Student } from "@schoolboxLearnosity/js/types/Student";
import type { StudentItem } from "@schoolboxLearnosity/js/types/StudentItem";

interface Props {
    student: Student; // the student to display
    item: StudentItem; // the item that's displayed
}
const props = defineProps<Props>();
</script>

<template>
    <div class="component-action">
        <section>
            <div>
                <button title="">Save</button>
            </div>
        </section>
    </div>
</template>

<style scoped></style>
