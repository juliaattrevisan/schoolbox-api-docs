<script setup lang="ts">
/**
 * To provide access to further actions on a result
 */

import { Result } from "../../js/types/Result";
import { ResultLink } from "../../js/types/ResultLink";

interface Props {
    result: Result; // the result to display
}
const props = defineProps<Props>();
</script>

<template>
    <sbx-pop-out :content-id="`dropdown-${props.result.id}`"></sbx-pop-out>
    <ul :id="`dropdown-${props.result.id}`" class="f-dropdown" data-dropdown-content>
        <li v-for="link in props.result._links" :key="link.id">
            <a :href="link.url" :class="link.icon">{{ link.title }}</a>
        </li>
    </ul>
</template>

<style scoped></style>
