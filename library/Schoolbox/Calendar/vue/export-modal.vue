<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
import RevealModal from "@schoolboxVue/components/reveal-modal";
import { getSearchString } from "@js/util/queryUtils";
import ExportUrlModal from "./export-url-modal";

export default {
    name: "export-modal",

    components: { ExportUrlModal, RevealModal },

    props: ["eventTypes", "groups", "campuses", "translations", "token", "eventExportUrl", "exportOptions"],

    data() {
        return {
            form: {
                export: "all",
                campus: "",
                group: "",
                event_type: "",
            },
        };
    },

    computed: {
        window: () => window,

        /**
         * The export events URL.
         *
         * @return {string}
         */
        exportUrl() {
            let values = Object.assign({}, this.form);

            // Add token to object
            values.token = this.token;

            // remove unused fields
            if (values["export"] !== "group") {
                delete values.group;
            }

            if (values["export"] !== "campus") {
                delete values.campus;
            }

            return (
                this.window.location.protocol +
                "//" +
                this.window.location.host +
                this.eventExportUrl +
                "?" +
                getSearchString(values)
            );
        },
    },

    methods: {
        /**
         * Open this modal.
         */
        open() {
            this.$refs.modal.open();
        },

        checkDropdownRequirement() {
            let values = Object.assign({}, this.form);

            /* Check that the corresponding dropdown has a meaningful value
             */
            const TYPE_GROUP = "group";
            const TYPE_CAMPUS = "campus";
            const TYPE_EVENTTYPE = "event_type";

            /* Non-dropdown options don't need additional checking
             */
            if (TYPE_CAMPUS !== values.export && TYPE_GROUP !== values.export && TYPE_EVENTTYPE !== values.export) {
                this.$refs.exportForm.submit();
            }

            /* Check a dropdown option was provided
             */
            if (TYPE_GROUP === values.export && "" !== values.group.toString()) {
                this.$refs.exportForm.submit();
            } else if (TYPE_CAMPUS === values.export && "" !== values.campus.toString()) {
                this.$refs.exportForm.submit();
            } else if (TYPE_EVENTTYPE === values.export && "" !== values.event_type.toString()) {
                this.$refs.exportForm.submit();
            }

            /* Do nothing for now
             */
            return false;
        },
    },
};
</script>

<template>
    <reveal-modal ref="modal" size="medium">
        <form class="top" :action="eventExportUrl" ref="exportForm">
            <section>
                <fieldset class="content radiolist">
                    <ul class="option-list">
                        <li>
                            <input type="radio" v-model="form.export" name="export" value="all" id="export-all" />
                            <label for="export-all">All events</label>
                        </li>
                        <li>
                            <input
                                type="radio"
                                v-model="form.export"
                                name="export"
                                value="all_external"
                                id="export-all_external"
                            />
                            <label for="export-all_external">All events (including external events)</label>
                        </li>
                        <li>
                            <input
                                type="radio"
                                v-model="form.export"
                                name="export"
                                value="personal"
                                id="export-personal"
                            />
                            <label for="export-personal">Personal events only</label>
                        </li>
                        <li>
                            <input
                                type="radio"
                                v-model="form.export"
                                name="export"
                                value="timetable"
                                id="export-timetable"
                            />
                            <label for="export-timetable">Timetable classes</label>
                        </li>
                        <li v-if="exportOptions.timetabledays">
                            <input
                                type="radio"
                                v-model="form.export"
                                name="export"
                                value="timetabledays"
                                id="export-timetableday"
                            />
                            <label for="export-timetableday">Timetable days</label>
                        </li>
                        <li v-if="exportOptions.duework">
                            <input
                                type="radio"
                                v-model="form.export"
                                name="export"
                                value="duework"
                                id="export-duework"
                            />
                            <label for="export-duework">{{ translations.dueWork.proper }}</label>
                        </li>
                        <li v-if="exportOptions.resources">
                            <input
                                type="radio"
                                v-model="form.export"
                                name="export"
                                value="resources"
                                id="export-resources"
                            />
                            <label for="export-resources">Resource Booking</label>
                        </li>
                        <li>
                            <input
                                type="radio"
                                v-model="form.export"
                                name="export"
                                value="external"
                                id="export-external"
                            />
                            <label for="export-external">External events</label>
                        </li>
                        <li v-if="exportOptions.eotc_all">
                            <input
                                type="radio"
                                v-model="form.export"
                                name="export"
                                value="eotc_all"
                                id="export-eotc-all"
                            />
                            <label for="export-eotc-all">{{ translations.eotc.proper }}</label>
                        </li>
                        <li>
                            <input type="radio" v-model="form.export" name="export" value="campus" id="export-campus" />
                            <label for="export-campus"
                                >Events for campus:
                                <select name="campus" v-model="form.campus">
                                    <option value="">-- select --</option>
                                    <option v-for="campus in campuses" :value="campus.id" :key="campus.id">
                                        {{ campus.name }}
                                    </option>
                                </select>
                            </label>
                        </li>
                        <li>
                            <input type="radio" v-model="form.export" name="export" value="group" id="export-group" />
                            <label for="export-group"
                                >Events for group:
                                <select name="group" v-model="form.group">
                                    <option value="">-- select --</option>
                                    <option v-for="group in groups" :value="group.id" :key="group.id">
                                        {{ group.name }}
                                    </option>
                                </select>
                            </label>
                        </li>
                        <li>
                            <input
                                type="radio"
                                v-model="form.export"
                                name="export"
                                value="event_type"
                                id="export-type"
                            />
                            <label for="export-type"
                                >Events by type:
                                <select name="event_type" v-model="form.event_type">
                                    <option value="">-- select --</option>
                                    <option v-for="eventType in eventTypes" :value="eventType.id" :key="eventType.id">
                                        {{ eventType.name }}
                                    </option>
                                </select>
                            </label>
                        </li>
                    </ul>
                </fieldset>
            </section>

            <input type="hidden" name="format" value="csv" />
            <input type="hidden" name="token" :value="token" />

            <ul class="flex-list buttons">
                <li><button @click.prevent="$refs.feedUrlModal.open">Generate URL</button></li>
                <li><input type="submit" value="Download CSV" @click.prevent="checkDropdownRequirement" /></li>
            </ul>
        </form>

        <export-url-modal ref="feedUrlModal" :url="exportUrl" />
    </reveal-modal>
</template>
