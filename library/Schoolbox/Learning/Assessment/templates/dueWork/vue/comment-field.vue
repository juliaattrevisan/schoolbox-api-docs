<script setup lang="ts">
/**
 * This is the equivalent of comment.twig
 * Renders an input where teachers can insert feedback comment
 */
import locale from "@js/terminology/rosettaStone";
import { EditorToolbar } from "@js/editor/types/editorTypes";
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
}

const props = withDefaults(defineProps<Props>(), {
    feedback: null,
    tabIndex: undefined,
    readonly: false,
    selfAssessment: false,
    selfReflection: false,
    blindMode: false,
    isRichText: false,
});

const comment = defineModel<string>("comment", { required: true });
const textareaEl = window.Vue.useTemplateRef<HTMLTextAreaElement>("textareaEl");

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
            },
            true,
            resolve,
            true, // sync with the comment ref
        );
    });
}

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
    const editor = window.sbProps.editors[`comment-${props.participant.user.id}`];
    if (editor) {
        await editor.destroy();
    }
});
</script>

<template>
    <label>
        Comment
        <textarea
            :id="`comment-${participant.user.id}`"
            ref="textareaEl"
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
