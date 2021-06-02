<script>

import axios from 'axios'

export default {
    name: 'class-list',
    props: {
        /**
         * The title of the subject for which the class list is shown, including
         * all of its class codes.
         */
        subjectTitle: {
            type: String,
            required: true,
        },

        /**
         * A list of class codes for this subject.
         */
        classCodeList: {
            type: Array,
            required: true,
            default: [],
            validator: (classCodes) => classCodes.every((classCode) => typeof classCode === 'string')
        },

        /**
         * Is the learning moments module enabled?
         *
         * This controls whether the "share learning moment" button shows.
         */
        learningMomentsEnabled: {
            type: Boolean,
            default: true,
        },

        /**
         * Is the badge module enabled?
         *
         * This controls whether the "set goals" and "award achievements"
         * buttons show.
         */
        badgesEnabled: {
            type: Boolean,
            default: true,
        },

        /**
         * Is the pastoral care module enabled?
         *
         * This controls whether the "add pastoral care record"  button shows.
         */
        pastoralEnabled: {
            type: Boolean,
            default: true,
        },

        /**
         * Is email from Schoolbox enabled?
         *
         * This controls whether the "compose email"  button shows.
         */
        mailEnabled: {
            type: Boolean,
            default: true,
        },

        /**
         * Is the current user able to email?
         *
         * This controls whether the "compose email"  button shows.
         *
         * @todo remove, and merge with mailEnabled
         */
        currentUserCanEmail: {
            type: Boolean,
            default: true,
        },

        /**
         * Localised label for the "add pastoral care record" button.
         */
        pastoralCareLabel: {
            type: String,
            default: 'Pastoral Care',
        },

        /**
         * Localised label for the "house" column of the student table.
         */
        houseLabel: {
            type: String,
            default: 'House',
        },

        /**
         * Should the "House" column be shown in the student table?
         */
        showHouseColumn: {
            type: Boolean,
            default: true,
        },

        /**
         * Should the "Tutor Group" column be shown in the student table?
         *
         * @todo consider replacing with a slot as this is used by St Patrick's
         *       custom work only
         */
        showTutorGroupColumn: {
            type: Boolean,
            default: false,
        },

        /**
         * Should the "Core Group" column be shown in the student table?
         *
         * @todo consider replacing with a slot as this is used by St Patrick's
         *       custom work only
         */
        showCoreGroupColumn: {
            type: Boolean,
            default: false,
        },

        /**
         * The address to set as the "Reply To" address for emails sent from
         * this component.
         */
        replyToEmail: {
            type: String,
            default: ''
        },

        /**
         * Localised label for the title of the "special needs" icon which may
         * show for students.
         */
        specialNeedsLabel: {
            type: String,
            default: ''
        },

        /**
         * Should medical alerts show for students?
         */
        showMedicalAlerts: {
            type: Boolean,
            default: false,
        },

        /**
         * The different email audiences which may be emailed to from this
         * component.
         */
        emailGroups: {
            type: Array,
            required: true,
            validator: (groups) => groups.every((group) => {
                if (typeof group === 'object') {
                    return 'label' in group && 'isChecked' in group && 'value' in group;
                }
                return false;
            })
        },

        /**
         * The query string parameter which contains the encoded return URL.
         *
         * Along with `encodedReturnUrl` this is used when generating a link to
         * learning moments and badges, so that the user may be redirected
         * somewhere after those forms are submitted.
         */
        returnUrlName: {
            type: String,
            required: true,
        },

        /**
         * The encoded return URL.
         *
         * Along with `returnUrlName` this is used when generating a link to
         * learning moments and badges, so that the user may be redirected
         * somewhere after those forms are submitted.
         */
        encodedReturnUrl: {
            type: String,
            required: true,
        },

        /**
         * The API endpoint which returns a list of students for this class.
         */
        getStudentListsEndpoint: {
            type: String,
            required: true,
        },

        /**
         * The API endpoint which displays a form for awarding achievements.
         */
        getAwardAsAchievementFormEndpoint: {
            type: String,
            required: true,
        },

        /**
         * The API endpoint which displays a form for awarding goals.
         */
        getSetAsGoalFormEndpoint: {
            type: String,
            required: true,
        },

        /**
         * The API endpoint which displays a form for creating learning moments.
         */
        getCreateLearningMomentFormEndpoint: {
            type: String,
            required: true,
        },
    },
    data() {
        return {
            studentLists: [],
            showSkeletonLoader: true,
            selectedStudents: [],
            studentsCheckboxElements: undefined,
            groupsCheckboxElements: [],
            sendEmailButtonDefaultText: 'Compose Email',
            sendEmailButtonText: null,
            formIsReady: false,
            actionsPlaceholder: true,
            loadingText: 'Please Wait...',
            noEmailsFoundText: 'Sorry we cannot send an email to the selected users as they do not have an email',
            currentClassCode: null,
        }
    },
    mounted: function () {
        let self = this;
        new Promise(function () {
            self.getStudents();
        });

        self.sendEmailButtonText = self.sendEmailButtonDefaultText;

        // Set the current selected class code
        self.setClassCode(window.location.hash.substring(1));

        // If the Back button is pressed on this page: set the class code
        window.addEventListener('popstate', () => self.setClassCode(window.location.hash.substring(1)));
    },
    methods: {
        getStudents: function () {
            let self     = this,
            studentLists = new Promise(function (resolve) {
                axios.get(self.getStudentListsEndpoint)
                    .then(function (response) {
                        // Set all students to checked by default
                        self.studentLists = response.data
                            .map(function (student) {
                                student.isChecked = true;
                                return student;
                            });
                        resolve();
                    })
                    .catch(function (error) {
                        console.log(error);
                    })
                    .finally(function () {
                        // Hide skeleton loader and show class list table
                        self.showSkeletonLoader = false;
                    });
            });

            studentLists.then(function () {
                setTimeout(function () {
                    self.studentsCheckboxElements = document.getElementsByName('students[]');
                    self.groupsCheckboxElements = document.getElementsByName('groups[]');
                    // Remove actions placeholder once everything is ready
                    self.actionsPlaceholder = false;
                }, 100);
            });
        },
        sendEmail: function () {
            let self = this;

            // While the system is preparing the email lists, disable the button
            self.enableSendEmailButton = false;
            self.sendEmailButtonText = self.loadingText;
            this.$refs.form.submit();
        },
        addPastoralRecord: function () {
            window.location.href = '/pastoral/student/0/record/insert?' + this.returnUrlName + '=' + this.encodedReturnUrl + '&duplicates=' + this.selectedStudentIds.join(',');
        },
        buildAwardAchievementUrl() {
            return this.buildAwardBadgeUrl(this.getAwardAsAchievementFormEndpoint);
        },
        buildSetGoalUrl() {
            return this.buildAwardBadgeUrl(this.getSetAsGoalFormEndpoint);
        },
        buildAwardBadgeUrl(endpoint) {
            let url = new URL(window.location.href);
            url.pathname = endpoint;
            url.searchParams.append(this.returnUrlName, this.encodedReturnUrl);
            url.searchParams.append('userIds', this.selectedStudentIds.join(','));
            return url.toString();
        },
        openMailDialog: function () {
            let self = this,
                dialog = $('#classList-email'),
                selectedStudents = [];

            // Load selected students into the dynamic user slot of this dialog
            this.studentLists.forEach(function (student) {
                if (student.synergy_id && self.selectedStudentExternalIds.includes(student.synergy_id)) {
                    selectedStudents.push(student);
                }
            });

            dialog.find('[data-relation="dynamicUsersSlot"]')
                .empty()
                .append(self.buildSelectedStudentsLinkList(selectedStudents));
            dialog.foundation('reveal', 'open');
        },
        openShareLearningMomentDialog: function () {
            // Set each link's href to the selected users
            let self = this,
                dialog = $('#classList-shareLearningMoment');
            dialog.find('a[data-action="shareLearningMoment"]').each(function () {
                let url = new URL(this.href);
                url.pathname = self.getCreateLearningMomentFormEndpoint;
                url.searchParams.append('userIds', self.selectedStudentLearningMomentsEnabledIds.join(','));
                this.href = url.href;
            });

            // Also update the "sharing on behalf of" part here
            let selectedStudents = [];
            self.studentLists.forEach(function (student) {
                if (student.id && self.selectedStudentLearningMomentsEnabledIds.includes(student.id)) {
                    selectedStudents.push(student);
                }
            });

            // Create the list of links
            let parts = self.buildSelectedStudentsLinkList(selectedStudents);

            // Shove it in the dynamic users slot
            dialog.find('[data-relation="dynamicUsersSlot"]').empty().append(parts);
            dialog.foundation('reveal', 'open');
        },

        /**
         * Build a list of links to the students in `students`.
         *
         * @param {Array} students
         * @return {Array}
         */
        buildSelectedStudentsLinkList: function (students) {
            // Create the list of links
            let parts = [];
            students.forEach(function (student) {
                parts
                    .push($('<a>')
                        .attr('href', '/search/user/' + student.id)
                        .text(student.firstname + ' ' + student.lastname));
                parts.push(', ');
            });

            // Slice off the last comma
            parts = parts.slice(0, -1);

            // Replace the last comma with "and"
            if (parts.length > 1) {
                parts.splice(-2, 1, ' and ');
            }
            return parts;
        },

        /**
         * Sets the current class code: students will be filtered out of the
         * displayed list depending on this value.
         *
         * @param {String|null} classCode
         */
        setClassCode(classCode) {
            if (this.classCodeList.includes(classCode)) {
                this.currentClassCode = classCode;
            } else {
                this.currentClassCode = null;
            }
        },

        /**
         * Is this student to be displayed, going by the current selected class
         * code?
         *
         * @param {Object} student
         * @return {Boolean}
         */
        isStudentInCurrentClassCode(student) {
            if (this.currentClassCode === null) {
                return true;
            }
            return student.classCodes.includes(this.currentClassCode);
        }
    },
    computed: {
        replyTo: function () {
            return '"' + this.subjectTitle + '"' + ' <' + this.replyToEmail + '>';
        },
        showCheckboxes: function () {
            return this.learningMomentsEnabled || this.mailEnabled;
        },
        allSelected: {
            get: function () {
                return this.studentsForCurrentClassCode.every(function (student) {
                    return student.isChecked;
                });
            },
            set: function (value) {
                // studentsForCurrentClassCode is a computed property without a
                // setter, so it can't be set directly.
                // Set studentLists instead.
                let self          = this;
                this.studentLists = self.studentLists.map(function (student) {
                    if (self.isStudentInCurrentClassCode(student)) {
                        student.isChecked = value;
                    }
                    return student;
                });
            }
        },
        studentsForCurrentClassCode() {
            // Which students are in the current class code?
            return this.studentLists.filter(this.isStudentInCurrentClassCode);
        },
        selectedStudentIds: function () {
            let ids = [],
                self = this;
            self.studentsForCurrentClassCode.forEach(function (student) {
                if (student.isChecked && student.id) {
                    ids.push(student.id);
                }
            });
            return ids;
        },
        selectedStudentLearningMomentsEnabledIds: function () {
            let ids = [],
                self = this;
            self.studentsForCurrentClassCode.forEach(function (student) {
                if (student.isChecked && student.learningMomentsEnabled) {
                    ids.push(student.id);
                }
            });
            return ids;
        },
        selectedStudentExternalIds: function () {
            let externalIds = [],
                self = this;
            self.studentsForCurrentClassCode.forEach(function (student) {
                if (student.isChecked && student.synergy_id) {
                    externalIds.push(student.synergy_id);
                }
            });
            return externalIds;
        },
        selectedGroups: function () {
            let groups = [],
                self = this;
            self.emailGroups.forEach(function (group) {
                if (group.isChecked) {
                    groups.push(group.value);
                }
            });
            return groups;
        },
        enableSendEmailButton: function () {
            return this.selectedGroups.length > 0;
        },
    },
    filters: {
        capitalise: function (val) {
            if (!val) {
                return '';
            }

            return val.replace(/(?:^|\s)\S/g, function (a) {
                return a.toUpperCase();
            });
        }
    }
}
</script>
<template>
    <div>
        <div class="small-12 island">
            <h1>{{ subjectTitle }}</h1>

            <dl class="tabs" v-if="classCodeList.length > 1">
                <dd :class="{active: currentClassCode === null}">
                    <a href="#" @click="setClassCode(null)">All class codes</a>
                </dd>
                <dd v-for="classCode in classCodeList" :key="classCode" :class="{active: currentClassCode === classCode}">
                    <a :href="'#' + classCode" @click="setClassCode(classCode)">{{ classCode }}</a>
                </dd>
            </dl>

            <section class="content">
                <ul class="flex-list flex-wrap buttons">
                    <li v-if="learningMomentsEnabled">
                        <a @click.prevent="openShareLearningMomentDialog"
                           :class="{disabled: selectedStudentLearningMomentsEnabledIds.length === 0, 'icon-share': true}"
                        >Share Learning Moment</a>
                    </li>
                    <li v-if="badgesEnabled">
                        <a :href="buildSetGoalUrl()"
                           :class="{disabled: selectedStudentIds.length === 0, 'icon-goal': true}"
                        >Set Goals</a>
                    </li>
                    <li v-if="badgesEnabled">
                        <a :href="buildAwardAchievementUrl()"
                           :class="{disabled: selectedStudentIds.length === 0, 'icon-achievement': true}"
                        >Award Achievements</a>
                    </li>
                    <li v-if="pastoralEnabled">
                        <a @click.prevent="addPastoralRecord"
                           :class="{disabled: selectedStudentIds.length === 0, 'icon-user': true}"
                        >Add {{ pastoralCareLabel }} Record</a></li>
                    <li v-if="mailEnabled && currentUserCanEmail">
                        <a @click.prevent="openMailDialog"
                           :class="{disabled: selectedStudentExternalIds.length === 0, 'icon-email': true}"
                        >Compose Email</a>
                    </li>
                </ul>
            </section>

            <template v-if="showSkeletonLoader" v-for="n in 3">
                <div class="list-item">
                    <div class="small-12 card skeleton">
                        <div class="img"></div>
                        <h3></h3>
                        <p class="meta"></p>
                    </div>
                </div>
            </template>

            <template v-if="!showSkeletonLoader">
                <table id="contact-table" v-if="studentsForCurrentClassCode.length">
                    <thead>
                    <tr>
                        <th v-if="showCheckboxes">
                            <input type="checkbox" id="select-all" v-model="allSelected"/>
                            <label for="select-all" class="checkbox-label"></label>
                        </th>
                        <th></th>
                        <th>Name</th>
                        <th class="show-for-large-up">Year Level</th>
                        <th class="show-for-large-up" v-if="showTutorGroupColumn">Tutor Group</th>
                        <th class="show-for-medium-up" v-if="showCoreGroupColumn">Core Group</th>
                        <th class="show-for-medium-up" v-if="showHouseColumn">{{ houseLabel }}</th>
                        <th class="show-for-medium-up">Last Visited</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(student, index) in studentsForCurrentClassCode">
                        <td v-if="showCheckboxes">
                            <input type="checkbox" name="students[]" :id="'id-' + index" v-model="student.isChecked"/>
                            <label :for="'id-' + index" class="checkbox-label"></label>
                        </td>
                        <td>
                            <img :src="'/portrait.php?id=' + student.id + '&size=square64'"
                                 :alt="student.lastname + ', ' + student.firstname">
                        </td>
                        <td>
                            <template v-if="student.id">
                                <a :href="'/search/user/' + student.id">
                                    {{ student.lastname + ', ' + student.firstname }}
                                </a>
                            </template>
                            <template v-else>
                                {{ student.lastname + ', ' + student.firstname }}
                            </template>
                            <span class="meta">({{ student.synergy_id }})</span>
                            <span class="show-for-print"><br>{{ student.email }}</span>

                            <p class="meta">
                                <slot name="studentMeta" v-bind:student="student">
                                    <span v-if="Number(student.EnglishSecondFlag) === 1"
                                          class="icon-eng-second-lang highlight"
                                          title="English as an Additional Language">
                                    </span>
                                    <span v-if="showMedicalAlerts && Number(student.StudentSpecialNeedsFlag) === 1"
                                          class="icon-bell warning"
                                          :title="specialNeedsLabel">
                                    </span>
                                    <span v-if="showMedicalAlerts && String(student.StudentMedicalAlert) !== ''"
                                          class="icon-alert alert"
                                          :title="'Medical Alert: ' + student.StudentMedicalAlert">
                                    </span>
                                </slot>
                            </p>

                            <ul data-dropdown-content class="f-dropdown" :id="'overflow' + student.id"
                                v-if="student.id && Object.keys(student.routes).length">
                                <slot name="studentMenuItems" v-bind:student="student">
                                    <li v-for="element in student.routes">
                                        <a :href="element.href" :class="element.class" :title="element.title">
                                            {{ element.label }}
                                        </a>
                                    </li>
                                </slot>
                            </ul>
                        </td>
                        <td class="show-for-large-up">{{ student.yearlevel }}</td>
                        <td class="show-for-medium-up" v-if="showTutorGroupColumn">{{ student.StudentTutorDescription }}</td>
                        <td class="show-for-medium-up" v-if="showCoreGroupColumn">{{ student.StudentForm }}</td>
                        <td class="show-for-medium-up" v-if="showHouseColumn">{{ student.house }}</td>
                        <td class="show-for-medium-up">{{ student.lastAccessTime }}</td>
                        <td class="no-pad">
                            <div class="actions-small-1" v-if="student.id && Object.keys(student.routes).length">
                                <nav>
                                    <a href="#" :data-dropdown="'overflow' + student.id" class="icon-more" title="Actions"></a>
                                </nav>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </template>
        </div>

        <div v-if="mailEnabled" id="classList-email" data-reveal aria-hidden="true" class="reveal-modal small">
            <h2>Compose Email</h2>

            <ul class="meta">
                <li>Composing for <span data-relation="dynamicUsersSlot"></span></li>
            </ul>

            <fieldset class="checklist">
                <legend>Recipients</legend>
                <ul class="option-list">
                    <li v-for="(group, index) in emailGroups" :key="index">
                        <input :id="group.label"
                               name="groups[]"
                               type="checkbox"
                               :value="group.value"
                               v-model="group.isChecked"/>
                        <label :for="group.label">{{ group.label }}</label>
                    </li>
                </ul>
            </fieldset>

            <form ref="form" action="/mail/createMail.php" method="post" v-on:submit.prevent class="class-list-form">
                <input type="hidden" name="replyto" :value="replyTo"/>
                <input type="hidden" id="selectedIds" name="selectedIds" :value="selectedStudentExternalIds.join(',')"/>
                <input type="hidden" id="selectedGroups" name="selectedGroups" :value="selectedGroups.join(',')"/>
                <button type="submit" class="button" :disabled="!enableSendEmailButton" @click="sendEmail">
                    {{ sendEmailButtonText }}
                </button>
            </form>
        </div>

        <div class="small-12 island" v-if="!showSkeletonLoader && !studentsForCurrentClassCode.length">
            <div class="empty-state">
                <i class="icon-class-list"></i>
                <p>Sorry, the system is unable to find any student in this class.</p>
            </div>
        </div>

        <template v-if="actionsPlaceholder">
            <!--
              Just a place holder. Cause currently when we remove this line, the actions doesn't work.
              Seems like when the page has loaded, foundation JS looks for this element right-away.
            -->
            <a href="#" data-dropdown="overflow" class="icon-more" title="Actions" style="display: none"></a>
        </template>

    </div>
</template>
