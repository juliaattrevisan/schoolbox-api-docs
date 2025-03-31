<script setup lang="ts">
/**
 * render a single pastoral badge with a number that represents the number of occurrences that have been recorded
 */
import type { StudentPastoralCount } from "@schoolboxLibrary/Pastoral/types/StudentPastoralCount";

interface Props {
    studentPastoralCount: StudentPastoralCount;
}

const props = defineProps<Props>();

window.Vue.onMounted(() => {
    // As vue loads the template after foundation has been initialised, we need to reinitialise the tooltips
    // @ts-expect-error -- jquery plugin
    // eslint-disable-next-line @typescript-eslint/no-unsafe-call -- see above
    $(document).foundation("tooltip", "reflow");
});
</script>
<template>
    <a :href="props.studentPastoralCount.destinationUrl">
        <span
            :class="`label neutral icon-${props.studentPastoralCount.icon}`"
            data-tooltip
            :title="props.studentPastoralCount.title"
            :style="{
                'background-color': props.studentPastoralCount.bgColor,
                color: props.studentPastoralCount.fgColor,
            }"
        >
            {{ props.studentPastoralCount.count }}
        </span>
    </a>
</template>

<style scoped>
span {
    margin-top: 5px;
    font-style: normal;
}

span[class*="icon-"]::before {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    left: 0.8rem;
}

.label {
    padding: 0.6rem 0.7rem 0.6rem 2.6rem;
}
</style>
