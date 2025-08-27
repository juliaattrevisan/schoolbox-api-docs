<script setup lang="ts">
/**
 * This is the equivalent of comment.twig
 * Renders an input where teachers can insert feedback comment
 */
import locale from "@js/terminology/rosettaStone";
import { EditorToolbar } from "@js/editor/types/editorTypes";
import type { Template } from "@js/editor/types/editorTypes";
import { useEditor } from "@schoolboxVue/composables/editor/useEditor";

interface Props {
    participant: {
        user: {
            id: number;
            givenName: string;
        };
    };
    tabIndex?: number;
    readonly?: boolean;
    selfAssessment?: boolean;
    selfReflection?: boolean;
    blindMode?: boolean;
    isRichText?: boolean;
    /** optional prefix for the textarea ID, `comment-[prefix]-[participant.user.id]` */
    prefix?: string;
    templates?: Template[];
}

const props = withDefaults(defineProps<Props>(), {
    feedback: null,
    tabIndex: undefined,
    readonly: false,
    selfAssessment: false,
    selfReflection: false,
    blindMode: false,
    isRichText: false,
    prefix: "",
    templates: undefined,
});

/* Allow the ref to be nullable for easier usage, but useEditor requires a string - null will be converted to an empty string */
const commentNullable = defineModel<string | null>("comment", { required: true });
const comment = window.Vue.computed({
    get: () => commentNullable.value ?? "",
    set: (value: string | null) => {
        commentNullable.value = value;
    },
});

const textareaEl = window.Vue.useTemplateRef<HTMLTextAreaElement>("textareaRef");

/**
 * Initialize the CKEditor instance
 */
async function initEditor(): Promise<void> {
    await new Promise((resolve) => {
        if (!textareaEl.value) throw new Error("Unable to initialise comment without a textarea element");

        useEditor(
            comment,
            textareaEl.value,
            {
                targetElements: [textareaEl.value],
                showWordCount: true,
                maxCharCount: window.sbProps.MAX_BYTES_MYSQL_MEDIUMTEXT,
                toolbar: props.selfAssessment ? EditorToolbar.COMMENT : EditorToolbar.COMMENT_AI,
                hasTokens: !props.selfAssessment,
                templates: props.templates,
            },
            true,
            resolve,
            true, // sync with the comment ref
        );
    });
}

const textareaId = window.Vue.computed(() => {
    const prefix = props.prefix ? `${props.prefix}-` : "";
    return `comment-${prefix}${props.participant.user.id}`;
});

const placeholder = window.Vue.computed(() => {
    if (props.selfAssessment) {
        return `Your ${locale.selfAssessment.singular()}`;
    }
    if (props.selfReflection) {
        return `Your ${locale.selfReflection.singular()}`;
    }
    if (props.readonly) {
        return "(no comment given)";
    }
    if (props.blindMode) {
        return "Feedback";
    }
    return `Feedback for ${props.participant.user.givenName}`;
});

window.Vue.onMounted(async () => {
    if (props.isRichText) {
        await initEditor();
    }
});

window.Vue.onUnmounted(async () => {
    const editor = window.sbProps.editors[textareaId.value];
    if (editor) {
        await editor.destroy();
    }
});
</script>

<template>
    <label>
        Comment
        <textarea
            :id="textareaId"
            ref="textareaRef"
            name="comment"
            :data-test="`assessment-feedback-comment-textarea-${participant.user.id}`"
            :placeholder="placeholder"
            rows="3"
            :tabindex="tabIndex || undefined"
            :readonly="readonly || undefined"
        ></textarea>
    </label>
</template>

<style scoped></style>
