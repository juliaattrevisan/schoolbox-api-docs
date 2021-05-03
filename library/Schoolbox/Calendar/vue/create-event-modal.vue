<script>

import RevealModal from '@schoolbox/reveal-modal'
import axios       from 'axios'
import { mixinFullCalendarHelper } from "@schoolbox/fullcalendar/mixinFullCalendarHelper";
import {ENDPOINTS} from '@schoolboxCalendarEvent/endpoints';

/* Vue select
    */
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";

/* Render custom template for Deselect icon/button
* It's not well documented, but you can trawl through closed PRs to figger out how
* https://github.com/sagalbot/vue-select/issues/994
*/
import Deselect from '@schoolbox/selectize/Deselect';
vSelect.props.components.default = () => ({ Deselect });

const EVENT_CREATED = 'eventCreated';

export default {

    name: "create-event-modal",

    components: {
        RevealModal,
        vSelect
    },

    props: [
        'userId',
        'canInvite',
        'eventTypes',
        // TODO why do I have to pass this method around? -because it's linked to the fullCalenderApi, which is not availble in this context
        'toMoment',
        'eventCreateUrl',
        'eventCreateFormUrl',
        'eventModifyFormUrl',
        'formatFormDate',
        'formatFormDatetime',
        'formatDisplayDate',
        'formatDisplayDatetime',
    ],

    data() {
        return {
            event: {},

            /* Vue Select
                */
            options: [],
            selected: []
        }
    },

    computed: {
        window: () => window,

        /**
         * Can the event form be submitted?
         *
         * @return {boolean}
         */
        isFormSubmittable() {
            return this.event.title && this.event.title.length !== 0;
        },

        /**
         * The event's date, as submitted via the form.
         *
         * @return {string}
         */
        formDate() {
            return this.event.date
                ? this.toMoment(this.event.date).format(
                    this.event.allDay
                        ? this.formatFormDate
                        : this.formatFormDatetime
                )
                : '';
        },

        /**
         * The event's date, as displayed on the modal.
         *
         * @return {string}
         */
        displayDate() {
            return this.event.date
                ? this.toMoment(this.event.date).format(
                    this.event.allDay
                        ? this.formatDisplayDate
                        : this.formatDisplayDatetime
                )
                : '';
        },


        /**
         * Compile object ids into csv
         */
        compileAttendeeIds() {
            let stack = [];

            for (let i = 0; i < this.selected.length; i++) {
                stack.push(this.selected[i].id);
            }

            return stack.join(',');
        }
    },

    created() {
        this.ENDPOINTS = ENDPOINTS;
    },

    methods: {

        /**
         * Open this modal.
         */
        open(event) {
            this.event = event;

            this.$refs.modal.open();

            /* Clear selectize contents
                */
            this.selected = [];
        },


        /**
         * Start searching for keyword
         *
         * @param query
         * @param loading
         */
        onSearch(query, loading) {
            loading(true);
            this.search(loading, query, this);
        },


        /**
         * Perform async request and load results into component
         */
        search: _.debounce(function(loading, query, vm) {
            axios.get(ENDPOINTS.URL_SELECTIZE, {
                params: {
                    query: query,
                    groupID: 0
                }
            })
            .then(function(response) {
                vm.options = response.data;
                loading(false);
            });
        }, 350),


        /* Post the event creation form.
            *
            * TODO consider whether this should be in the base FullCalendar component
            *
            * @param form The form that's being submitted
            */
        createNewEvent(form) {
            let self = this;
            self.$refs.modal.close();
            axios.request({
                url:          form.action,
                data:         this.window.jQuery(form).serialize(),
                method:       form.method,
                responseType: 'json',
            }).then(function (response) {
                // Add this event to the calendar
                self.$emit(EVENT_CREATED, response.data);
            });
        },

        /**
         * Redirect the browser to the full event modify form.
         */
        redirectToEventForm() {
            // TODO don't use jQuery form serialize
            this.window.location.href = this.eventCreateFormUrl
                + '?returnUrl=' + btoa(window.location.pathname)
                + '&' + this.window.jQuery(this.$refs.createEventForm).serialize();
        },

    },

}

</script>

<template>
    <reveal-modal ref="modal">
        <!-- create a tab Vue component? Currently they just work magically -->
        <dl class="tabs" data-tab>
            <dd class="active"><a href="#createEventPanel">Create Event</a></dd>
            <dd><a href="#createTaskPanel">Create Task</a></dd>
        </dl>
        <div class="tabs-content">
            <div class="content active" id="createEventPanel" aria-hidden="false">
                <form ref="createEventForm"
                      method="post"
                      :action="eventCreateUrl"
                      @submit.prevent="createNewEvent($refs.createEventForm)"
                      class="top"
                >
                    <input name="userId"  type="hidden" :value="userId" />
                    <input name="allDay"  type="hidden" :value="Number(event.allDay)" />
                    <input name="date"    type="hidden" :value="formDate" />
                    <input name="variant" type="hidden" value="event" />

                    <section>
                        <h3>On <span>{{ displayDate }}</span></h3>

                        <label class="required" for="createEventBubbleTitle">
                            Title
                        </label>
                        <input id="createEventBubbleTitle" name="title" type="text" v-model.trim="event.title" />

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
                            <v-select class="schoolbox-vue-selectize" :options="options" :clear-search-on-blur="function(clearSearchOnSelect, multiple) { return true; }" :clear-search-on-select="false" :close-on-select="false" multiple @search="onSearch" label="name" placeholder="Search for people to invite" v-model="selected">
                                <template slot="option" slot-scope="option">
                                    <span class="d-center">
                                        {{ option.name }}
                                    </span>
                                </template>
                                <template slot="selected-option" slot-scope="option">
                                    <span class="selected d-center">
                                        {{ option.first_name }} {{ option.last_name }}
                                    </span>
                                </template>
                            </v-select>
                            <input type="hidden" name="guests" :value="compileAttendeeIds" />
                        </template>
                    </section>
                    <ul class="flex-list buttons">
                        <li><button @click.prevent="redirectToEventForm($event)">Edit Event</button></li>
                        <li><input type="submit"
                                   :disabled="!isFormSubmittable"
                                   value="Create Event" /></li>
                    </ul>
                </form>
            </div>
            <div class="content" id="createTaskPanel" aria-hidden="true" tabindex="-1">
                <form ref="createTaskForm"
                      method="post"
                      :action="eventCreateUrl"
                      @submit.prevent="createNewEvent($refs.createTaskForm)"
                >
                    <input name="userId"  type="hidden" :value="userId" />
                    <input name="allDay"  type="hidden" :value="Number(event.allDay)" />
                    <input name="date"    type="hidden" :value="formDate" />
                    <input name="variant" type="hidden" value="task" />

                    <section>
                        <h3>Due <span>{{ displayDate }}</span></h3>

                        <label class="required" for="createTaskBubbleTitle">
                            Task
                        </label>
                        <input id="createTaskBubbleTitle" name="title" type="text" v-model.trim="event.title" />

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
                            <textarea name="detail" v-model="event.detail"></textarea>
                        </label>
                    </section>
                    <div class="row">
                        <div class="small-12 columns no-pad">
                            <input type="submit"
                                   :disabled="!isFormSubmittable"
                                   value="Create Task" />
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </reveal-modal>
</template>
