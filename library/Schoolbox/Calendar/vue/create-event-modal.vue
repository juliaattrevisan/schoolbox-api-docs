<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import RevealModal from "@schoolboxVue/components/reveal-modal";
import Toast from "@schoolboxVue/components/toast.vue";
import { ENDPOINTS } from "@schoolboxCalendarEvent/endpoints";
import { injectScript, isScriptAdded } from "@js/util/domUtils";
import { EditorEvent } from "@js/editor/event/editorEvent";
import { EditorToolbar } from "@js/editor/types/editorTypes";

export default {
    name: "create-event-modal",

    components: {
        RevealModal,
        Toast,
        SearchSelect,
    },

    props: [
        "userId",
        "canInvite",
        "eventTypes",
        "eventCreateUrl",
        "eventCreateFormUrl",
        "eventModifyFormUrl",
        "formatFormDate",
        "formatFormDatetime",
        "formatDisplayDate",
        "formatDisplayDatetime",
    ],

    emits: ["eventCreated"],

    data() {
        return {
            event: {},
            selected: [],
        };
    },

    computed: {
        window: () => window,

        /**
         * Can the event form be submitted?
         *
         * @return {boolean}
         */
        isFormSubmittable() {
            return this.event.title && this.event.title.length !== 0 && !this.isProcessing;
        },

        /**
         * The event's date, as submitted via the form.
         *
         * @return {string}
         */
        formDate() {
            return this.event.date
                ? dayjs(this.event.date).format(this.event.allDay ? this.formatFormDate : this.formatFormDatetime)
                : "";
        },

        /**
         * The event's date, as displayed on the modal.
         *
         * @return {string}
         */
        displayDate() {
            return this.event.date
                ? dayjs(this.event.date).format(this.event.allDay ? this.formatDisplayDate : this.formatDisplayDatetime)
                : "";
        },

        /**
         * Compile object ids into csv
         */
        compileAttendeeIds() {
            let stack = [];

            for (let i = 0; i < this.selected.length; i++) {
                stack.push(this.selected[i].id);
            }

            return stack.join(",");
        },
    },

    created() {
        this.ENDPOINTS = ENDPOINTS;
    },

    methods: {
        /**
         * Get the endpoint for the user search
         * @returns {String}- The endpoint for the user search
         */
        getEndpoint() {
            const searchUrl = new URL(this.ENDPOINTS.URL_SELECTIZE, window.location.href).toString();
            const searchParams = new URLSearchParams({ query: "", groupID: 0 }).toString();
            return `${searchUrl}?${searchParams}&`;
        },

        /**
         * Open this modal.
         */
        open(event) {
            this.event = event;

            this.$refs.modal.open();

            //loadCkeditor
            this.initCKE();

            /* Clear selectize contents
             */
            this.selected = [];
        },

        /**
         * Post the event creation form.
         *
         * TODO consider whether this should be in the base FullCalendar component
         *
         * @param form The form that's being submitted
         *
         */
        async createNewEvent(form) {
            this.isProcessing = true;
            const formContent = new FormData(form);
            formContent.set("detail", window.sbProps.editors["detail"].getData());
            this.$refs.toast.activate("Saving...");

            try {
                const response = await fetch(form.action, {
                    method: form.method,
                    body: formContent,
                    responseType: "json",
                    headers: {
                        Accept: "application/json",
                    },
                });
                const responseData = await response.json();
                if (response.ok) {
                    this.$refs.modal.close();
                    this.$emit("eventCreated", responseData);
                    this.$refs.toast.timeout("Created", true, 2000);
                } else {
                    this.$refs.toast.timeout("Unable to create event / task. " + responseData.message, false, 3000);
                }
            } catch (error) {
                this.$refs.toast.timeout("Unable to create event / task. " + error.message, false, 3000);
                console.warn(error);
            } finally {
                this.isProcessing = false;
            }
        },

        /**
         * Redirect the browser to the full event modify form.
         */
        redirectToEventForm() {
            // TODO don't use jQuery form serialize
            this.window.location.href =
                this.eventCreateFormUrl +
                "?returnUrl=" +
                btoa(window.location.pathname) +
                "&" +
                $(this.$refs.createEventForm).serialize();
        },

        /**
         * Initialise CKEditor
         */
        async initCKE() {
            const editorScript = `${window.cdnPrefix}/js/bundle/editor.js?v=${window.siteVersion}`;
            if (!isScriptAdded(editorScript)) {
                await injectScript(editorScript);
            }

            // if an editor already exists, just clear its content and avoid creating another
            const detailEditor = window.sbProps.editors["detail"];
            if (detailEditor) {
                detailEditor.setData("");
                return;
            }

            // otherwise lets set an editor up
            document.dispatchEvent(
                new CustomEvent(EditorEvent.EDITOR__CREATE, {
                    detail: {
                        targetElements: ["#detail"],
                        toolbar: EditorToolbar.SIMPLE,
                        removeFromToolbar: ["link"],
                    },
                }),
            );
        },
    },
};
</script>

<template>
    <toast ref="toast"></toast>
    <reveal-modal ref="modal">
        <!-- create a tab Vue component? Currently they just work magically -->
        <dl class="tabs" data-tab>
            <dd class="active"><a href="#createEventPanel">Create Event</a></dd>
            <dd><a href="#createTaskPanel">Create Task</a></dd>
        </dl>
        <div class="tabs-content">
            <div class="content active" id="createEventPanel" aria-hidden="false">
                <form
                    ref="createEventForm"
                    method="post"
                    :action="eventCreateUrl"
                    @submit.prevent="createNewEvent($refs.createEventForm)"
                    class="top"
                >
                    <input name="userId" type="hidden" :value="userId" />
                    <input name="allDay" type="hidden" :value="Number(event.allDay)" />
                    <input name="date" type="hidden" :value="formDate" />
                    <input name="variant" type="hidden" value="event" />

                    <section>
                        <h3>
                            On <span>{{ displayDate }}</span>
                        </h3>

                        <label class="required" for="createEventBubbleTitle"> Title </label>
                        <input
                            id="createEventBubbleTitle"
                            name="title"
                            maxlength="250"
                            type="text"
                            v-model.trim="event.title"
                        />

                        <label>
                            Type
                            <select name="type" v-model="event.type">
                                <option v-for="eventType in eventTypes" :value="eventType.id" :key="eventType.id">
                                    {{ eventType.name }}
                                </option>
                            </select>
                        </label>

                        <template v-if="canInvite">
                            <label>Invite Guests</label>
                            <search-select
                                class="l-pad-m--bottom"
                                v-model:selected="selected"
                                :endpoint="getEndpoint()"
                                label="Search"
                                label-id="userInvite"
                                placeholder=""
                            >
                                <template #meta>
                                    <div class="meta">Search for people to invite guests</div>
                                </template></search-select
                            >
                            <input type="hidden" name="guests" :value="compileAttendeeIds" />
                        </template>
                    </section>
                    <ul class="flex-list buttons">
                        <li><button @click.prevent="redirectToEventForm($event)">Edit Event</button></li>
                        <li>
                            <input
                                type="submit"
                                :disabled="!isFormSubmittable"
                                value="Create Event"
                                data-test="create-event-modal-create-button"
                            />
                        </li>
                    </ul>
                </form>
            </div>
            <div class="content" id="createTaskPanel" aria-hidden="true" tabindex="-1">
                <form
                    ref="createTaskForm"
                    method="post"
                    :action="eventCreateUrl"
                    @submit.prevent="createNewEvent($refs.createTaskForm)"
                >
                    <input name="userId" type="hidden" :value="userId" />
                    <input name="allDay" type="hidden" :value="Number(event.allDay)" />
                    <input name="date" type="hidden" :value="formDate" />
                    <input name="variant" type="hidden" value="task" />

                    <section>
                        <h3>
                            Due <span>{{ displayDate }}</span>
                        </h3>

                        <label class="required" for="createTaskBubbleTitle"> Task </label>
                        <input
                            id="createTaskBubbleTitle"
                            name="title"
                            maxlength="250"
                            type="text"
                            v-model.trim="event.title"
                        />

                        <label>
                            Type
                            <select name="type" v-model="event.type">
                                <option v-for="eventType in eventTypes" :value="eventType.id" :key="eventType.id">
                                    {{ eventType.name }}
                                </option>
                            </select>
                        </label>

                        <label class="detail">
                            Note
                            <textarea id="detail" name="detail"></textarea>
                        </label>
                    </section>
                    <div class="row">
                        <div class="small-12 columns no-pad">
                            <input type="submit" :disabled="!isFormSubmittable" value="Create Task" />
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </reveal-modal>
</template>
