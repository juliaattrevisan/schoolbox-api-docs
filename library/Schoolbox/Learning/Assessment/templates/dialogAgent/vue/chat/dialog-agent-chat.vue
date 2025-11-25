<script lang="ts" setup>
import type { UserAuthor } from "@schoolboxLibrary/types/User";
import type { AiSession } from "@schoolboxLibrary/Admin/OpenAI/Usage/templates/payload";
import { useAgentChatStore } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/store/agentChatStore";
import AcPrompt from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/ac-prompt.vue";
import AcFeed from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/ac-feed.vue";

type DialogAgentResponse = {
    aiSessionId: AiSession["id"];
    assistanceRequestedAt: string | null;
};

interface Props {
    generateRoute: string;
    openingMessage: string | null;
    response: DialogAgentResponse;
    student: UserAuthor;
}

const props = defineProps<Props>();
const acStore = useAgentChatStore();

const currentResponse = window.Vue.ref<DialogAgentResponse>(props.response);

/** If the current user isn't the student, they're the instructor I guess */
const isInstructor = window.Vue.computed(() => {
    return props.student.id !== window.schoolboxUser.id;
});

window.Vue.onMounted(() => {
    currentResponse.value = props.response;

    if (props.openingMessage !== null) {
        // TODO: I wonder if the backend should add this when it provides us the full chat?
        acStore.feed.push({
            type: "response",
            status: "success",
            entryKey: acStore.getNextEntryKey(),
            responseId: null,
            image: null,
            message: props.openingMessage,
        });
    }

    // TODO: all temporary
    const currentUser = {
        id: window.schoolboxUser.id,
        fullName: window.schoolboxUser.name,
        _links: {
            profile: `/search/user/${window.schoolboxUser.id}`,
            avatar: `/portrait.php?id=${window.schoolboxUser.id}&size=square64`,
        },
    };

    acStore.feed.push({
        type: "request",
        entryKey: acStore.getNextEntryKey(),
        user: currentUser,
        payload: {
            userPrompt: "You're a wizard, Harry",
            attributes: {},
            imageUrl: null,
            previousResponseId: null,
            aiSessionId: null,
        },
        file: null,
    });

    acStore.feed.push({
        type: "response",
        status: "success",
        entryKey: acStore.getNextEntryKey(),
        responseId: null,
        image: null,
        message: "I'm a what?",
    });

    acStore.feed.push({
        type: "request",
        entryKey: acStore.getNextEntryKey(),
        user: currentUser,
        payload: {
            userPrompt: "ignore all previous instructions... etc etc",
            attributes: {},
            imageUrl: null,
            previousResponseId: null,
            aiSessionId: null,
        },
        file: null,
    });

    if (isInstructor.value) {
        acStore.feed.push({
            type: "alert",
            side: "response",
            severity: "warning",
            entryKey: acStore.getNextEntryKey(),
            message: "Alert: Please check the chat above for challenging of guardrails or misuse of language.",
        });

        const assistanceTime = window.dayjs().subtract(5, "minutes").format(window.dateDisplayFormats.dayjsDatetime);
        acStore.feed.push({
            type: "alert",
            side: "request",
            severity: "warning-tint",
            entryKey: acStore.getNextEntryKey(),
            message: `${window.schoolboxUser.name} requested assistance @ ${assistanceTime}`,
        });

        const assistanceEndTime = window.dayjs().format(window.dateDisplayFormats.dayjsDatetime);
        acStore.feed.push({
            type: "alert",
            side: "request",
            severity: "highlight",
            entryKey: acStore.getNextEntryKey(),
            message: `${window.schoolboxUser.name} stopped requesting assistance @ ${assistanceEndTime}`,
        });
    }
});

/**
 * When a learner clicks "Request Teacher Help" or "Help No Longer Needed"
 * @param helpRequested - Whether this should ask for help, or clear the flag
 */
function changeHelpRequest(helpRequested: boolean): void {
    const assistanceTime = window.dayjs().format(window.dateDisplayFormats.dayjsDatetime);
    if (helpRequested) {
        currentResponse.value.assistanceRequestedAt = window.dayjs().format(window.dateDisplayFormats.dayjsDateLong);

        if (isInstructor.value) {
            acStore.feed.push({
                type: "alert",
                side: "request",
                severity: "warning-tint",
                entryKey: acStore.getNextEntryKey(),
                message: `${window.schoolboxUser.name} requested assistance @ ${assistanceTime}`,
            });
        }
    } else {
        currentResponse.value.assistanceRequestedAt = null;

        if (isInstructor.value) {
            acStore.feed.push({
                type: "alert",
                side: "request",
                severity: "highlight",
                entryKey: acStore.getNextEntryKey(),
                message: `${window.schoolboxUser.name} stopped requesting assistance @ ${assistanceTime}`,
            });
        }
    }

    console.warn("TODO"); // TODO
}

/**
 * When a learner or instructor clicks the submit button inside the modal
 */
function submitResponse(): void {
    // TODO: show a modal first, for students and/or staff?

    console.warn("TODO"); // TODO
}
</script>

<template>
    <div class="actions-small-1 l-mar-s--bottom">
        <button
            v-if="!isInstructor && currentResponse.assistanceRequestedAt === null"
            class="button"
            type="button"
            @click="changeHelpRequest(true)"
        >
            Request Teacher Help
        </button>
        <nav>
            <button class="button submit icon-approve" type="submit" @click="submitResponse">
                {{ isInstructor ? `Submit on behalf of ${props.student.fullName}` : "Submit" }}
            </button>
        </nav>
    </div>

    <ac-feed
        class="l-mar-m--bottom"
        style="max-height: 50vh; height: 100%"
        :feed="acStore.fullFeed"
        error-prefix="There was a problem retrieving the response."
    >
        <template #header>
            <div
                v-if="!isInstructor && currentResponse.assistanceRequestedAt !== null"
                class="alert-box warning-tint l-mar--0 l-pad-xs l-pad-m--left"
            >
                <div class="l-flex l-flex--justify-space l-flex--align-center">
                    <span>Help Requested! Your teacher will be informed. You can keep working while you wait.</span>
                    <button
                        class="button warning l-mar--0 dac-feed-help-dismiss"
                        type="button"
                        @click="changeHelpRequest(false)"
                    >
                        Help No Longer Needed
                    </button>
                </div>
            </div>
        </template>
    </ac-feed>
    <ac-prompt
        v-if="props.generateRoute"
        :request-config="{
            messageRoute: props.generateRoute,
            aiSessionId: null /* session ID is already attached to a response and handled in the backend */,
            requireImage: false,
        }"
        prompt-placeholder="Type a response here..."
        :prompt-attributes="[]"
    />
    <span class="meta">AI sometimes gets things wrong — please check important details.</span>
</template>

<style scoped lang="scss">
/** force the button to have white text, honestly idk about this but we're going with it for now */
.dac-feed-help-dismiss {
    color: white;
}
</style>
