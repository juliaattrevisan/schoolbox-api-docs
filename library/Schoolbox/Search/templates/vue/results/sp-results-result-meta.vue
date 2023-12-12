<script setup lang="ts">
/**
 * To display the search results title
 */

import { Result } from "../../js/types/Result";
import { ResultTypes } from "../../js/types/ResultTypes";

interface Props {
    result: Result; // the result to display
    isSubResult?: boolean; // whether this is a sub result
}
const props = withDefaults(defineProps<Props>(), {
    isSubResult: false,
});

/**
 * Using the result type, return the type title
 * @returns {string} the type title
 */
const typeTitle = window.Vue.computed(() => {
    const typeTitleOptions = {
        assessment: "Assessment",
        "assessment.dueWork": "Due work",
        "assessment.lessonPlan": "Lesson plan",
        "assessment.project": "Project",
        "assessment.lti": "LTI",
        "assessment.task": "Task",
        "assessment.quiz": "Quiz",
        badge: "Badge",
        blog: "Blog",
        event: "Event",
        file: "File",
        "file.audio": "Audio",
        "file.document": "Document",
        "file.image": "Image",
        "file.video": "Video",
        "file.other": "File",
        forum: "Forum",
        link: "Link",
        news: "News",
        page: "Page",
        "page.class": "Class",
        "page.course": "Course",
        "page.homepage": "Homepage",
        "page.unit": "Unit",
        "page.folder": "Folder",
        "page.group": "Group",
        textbox: "Textbox",
        tile: "Tile",
        user: "User",
        "user.guest": "Guest",
        "user.parent": "Parent",
        "user.staff": "Staff",
        "user.student": "Student",
    };
    return typeTitleOptions[props.result.type] || props.result.type;
});

/**
 * To display the date in format
 * @returns {string} the formatted date
 */
function getDateShort() {
    return window.dayjs(props.result.date).format("DD/MM/YYYY");
}

/**
 * To display the date in format
 * @returns {string} the formatted date
 */
function getDateLong() {
    return window.dayjs(props.result.date).format("dddd, MMMM D, YYYY");
}
</script>

<template>
    <span v-if="props.result.type === ResultTypes.NEWS" class="meta"
        >{{ typeTitle }} | Posted by
        <a :href="props.result.creator?._links?.profile">{{ props.result.creator?.fullName }}</a> on
        {{ getDateShort() }}</span
    >
    <span v-else-if="props.result.type.includes(ResultTypes.FILE)" class="meta"
        >{{ typeTitle }} | Created by
        <a :href="props.result.creator?._links?.profile">{{ props.result.creator?.fullName }}</a> on
        {{ getDateShort() }}</span
    >
    <span v-else-if="props.result.type === ResultTypes.EVENT" class="meta">{{ typeTitle }} | {{ getDateLong() }}</span>
    <span v-else-if="props.result.context.length !== 0 && !props.isSubResult" class="meta"
        >{{ typeTitle }} |
        <template v-for="(context, index) in props.result.context" :key="context.id"
            ><a :href="context._links.view">{{ context.title }}</a
            ><template v-if="index < props.result.context.length - 1"> > </template>
        </template></span
    >
    <span v-else class="meta"
        >{{ typeTitle }}<template v-if="props.result.meta"> | {{ props.result.meta }}</template></span
    >
</template>

<style scoped></style>
