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

const linksTest = window.Vue.ref<ResultLink[]>([
    {
        id: 0,
        title: "Class List",
        url: "/learning/class/80",
        icon: "icon-class-list",
    },
    {
        id: 1,
        title: "Class Markbook",
        url: "/learning/markbook/class/80/term/2023-1",
        icon: "icon-curriculum",
    },
    {
        id: 2,
        title: "Badge Report",
        url: "/learning/badge/award/list/80",
        icon: "icon-achievement",
    },
]);
</script>

<template>
    <sbx-pop-out :content-id="`dropdown-${props.result.id}`"></sbx-pop-out>
    <ul :id="`dropdown-${props.result.id}`" class="f-dropdown" data-dropdown-content>
        <li v-for="link in linksTest" :key="link.id">
            <a :href="link.url" :class="link.icon">{{ link.title }}</a>
        </li>
    </ul>
</template>

<style scoped></style>
