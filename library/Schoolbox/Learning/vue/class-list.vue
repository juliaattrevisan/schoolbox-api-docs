<script>

import axios from 'axios'

export default {
  name: 'class-list',
  props: {
    subjectTitle: {
      type: String,
      required: true,
    },
    classCodeList: {
      type: Array,
      required: true,
      default: [],
    },
    learningMomentsEnabled: {
      type: Boolean,
      default: true,
    },
    badgesEnabled: {
      type: Boolean,
      default: true,
    },
    pastoralEnabled: {
      type: Boolean,
      default: true,
    },
    mailEnabled: {
      type: Boolean,
      default: true,
    },
    currentUserCanEmail: {
      type: Boolean,
      default: true,
    },
    localePastoralCare: {
      type: String,
      default: 'Pastoral Care',
    },
    replyToEmail: {
      type: String,
      default: ''
    },
    specialNeeds: {
      type: String,
      default: ''
    },
    showMedicalAlerts: {
      type: Boolean,
      default: false,
    },
    emailGroups: {
      type: Array,
      required: true,
    },
    returnUrlName: {
      type: String,
      required: true,
    },
    getStudentListsEndpoint: {
      type: String,
      required: true,
    },
    encodedReturnUrl: {
      type: String,
      required: true,
    },
    getAwardAsAchievementFormEndpoint: {
      type: String,
      required: true,
    },
    getSetAsGoalFormEndpoint: {
      type: String,
      required: true,
    },
    getCreateEvidenceFormEndpoint: {
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
    }
  },
  mounted: function() {
    var self = this;

    new Promise(function () {
      self.getStudents();
    });

    self.sendEmailButtonText = self.sendEmailButtonDefaultText;
  },
  methods: {
    getStudents: function () {
      var self = this;

      var studentLists;

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
            }).finally(function() {
          self.showSkeletonLoader = false; // Hide skeleton loader and show class list table
        });
      });

      studentLists.then(function() {
        setTimeout(function() {
          self.studentsCheckboxElements = document.getElementsByName('students[]');
          self.groupsCheckboxElements = document.getElementsByName('groups[]');
          self.actionsPlaceholder = false; // Remove actions placeholder once everything is ready
        }, 100);
      });
    },
    sendEmail: function() {
      var self = this;

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
      let url      = new URL(window.location.href);
      url.pathname = endpoint;
      url.searchParams.append(this.returnUrlName, this.encodedReturnUrl);
      url.searchParams.append('userIds', this.selectedStudentIds.join(','));
      return url.toString();
    },
    openMailDialog: function () {
      let self             = this,
          dialog           = $('#classList-email'),
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
    openShareLearningMomentDialog: function() {
      // Set each link's href to the selected users
      let self     = this,
          dialog   = $('#classList-shareLearningMoment');
      // url      = new URL(window.location.href),
      // pathname = '/learning/evidence/user/' + self.selectedStudentIds.join(',') + '/create';
      dialog.find('a[data-action="shareLearningMoment"]').each(function () {
        let url      = new URL(this.href);
        url.pathname = self.getCreateEvidenceFormEndpoint;
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
  },
  computed: {
    replyTo: function() {
      return '"' + this.subjectTitle + '"' + ' <' + this.replyToEmail + '>';
    },
    showCheckboxes: function() {
      return this.learningMomentsEnabled || this.mailEnabled;
    },
    allSelected: {
      get: function () {
        return this.studentLists.every(function (student) {
          return student.isChecked;
        });
      },
      set: function (value) {
        this.studentLists = this.studentLists.map(function (student) {
          student.isChecked = value;
          return student;
        });
      }
    },
    selectedStudentIds: function () {
      let ids  = [],
          self = this;
      self.studentLists.forEach(function (student) {
        if (student.isChecked && student.id) {
          ids.push(student.id);
        }
      });
      return ids;
    },
    selectedStudentLearningMomentsEnabledIds: function () {
      let ids  = [],
          self = this;
      self.studentLists.forEach(function (student) {
        if (student.isChecked && student.learningMomentsEnabled) {
          ids.push(student.id);
        }
      });
      return ids;
    },
    selectedStudentExternalIds: function () {
      let externalIds = [],
          self        = this;
      self.studentLists.forEach(function (student) {
        if (student.isChecked && student.synergy_id) {
          externalIds.push(student.synergy_id);
        }
      });
      return externalIds;
    },
    selectedGroups: function () {
      let groups = [],
          self   = this;
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

      return val.replace(/(?:^|\s)\S/g, function(a) { return a.toUpperCase(); });
    }
  }
}
</script>
<template>
  <div>
    <div class="small-12 island">
      <h1>{{ subjectTitle }}</h1>

      <dl class="sub-nav" v-if="classCodeList.length > 1">
        <dd class="active">
          <a href="#">All class codes</a>
        </dd>
        <dd class="active" v-for="classCode in classCodeList">
          <a href="#">{{ classCode }}</a>
        </dd>
      </dl>

      <!--    {% block header %}-->
      <section class="content">
        <ul class="flex-list flex-wrap buttons">
          <li v-if="learningMomentsEnabled">
            <a @click.prevent="openShareLearningMomentDialog"
               :class="{disabled: selectedStudentLearningMomentsEnabledIds.length === 0, 'icon-share': true}">Share
              Learning Moment</a>
          </li>
          <li v-if="badgesEnabled">
            <a :href="buildSetGoalUrl()" :class="{disabled: selectedStudentIds.length === 0, 'icon-goal': true}">Set
              Goals</a>
          </li>
          <li v-if="badgesEnabled">
            <a :href="buildAwardAchievementUrl()"
               :class="{disabled: selectedStudentIds.length === 0, 'icon-achievement': true}">Award Achievements</a>
          </li>
          <li v-if="pastoralEnabled">
            <a @click.prevent="addPastoralRecord"
               :class="{disabled: selectedStudentIds.length === 0, 'icon-user': true}"
            >Add {{ localePastoralCare }} Record</a></li>
          <li v-if="mailEnabled && currentUserCanEmail">
            <a @click.prevent="openMailDialog"
               :class="{disabled: selectedStudentExternalIds.length === 0, 'icon-email': true}">Compose Email</a>
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
      <!--    {% endblock %}-->

      <table id="contact-table" v-if="!showSkeletonLoader && studentLists.length">
        <thead>
        <!--      {% block table_header %}-->
        <tr>
          <th v-if="showCheckboxes">
            <input type="checkbox" id="select-all" v-model="allSelected"/>
            <label for="select-all" class="checkbox-label"></label>
          </th>
          <th></th>
          <th>Name</th>
          <th class="show-for-large-up">Year Level</th>
          <th class="show-for-medium-up">House</th>
          <th class="show-for-medium-up">Last Visited</th>
          <th></th>
        </tr>
        <!--      {% endblock %}-->
        </thead>
        <tbody>
        <template v-if="studentLists.length > 0">
          <tr v-for="(student, index) in studentLists">
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

<!--              {% block meta %}-->
              <p class="meta">
                <span v-if="Number(student.EnglishSecondFlag) === 1"
                      class="icon-eng-second-lang highlight"
                      title="English as an Additional Language">
                </span>
                <span v-if="showMedicalAlerts && Number(student.StudentSpecialNeedsFlag) === 1"
                      class="icon-bell warning"
                      :title="specialNeeds">
                </span>
                <span v-if="showMedicalAlerts && String(student.StudentMedicalAlert) !== ''"
                      class="icon-alert alert"
                      :title="'Medical Alert: ' + student.StudentMedicalAlert">
                </span>
              </p>
<!--              {% endblock %}-->

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
<!--            {% block tutor_group %}-->
            <td class="show-for-medium-up">{{ student.house }}</td>
<!--            {% endblock %}-->
<!--            {% block core_group %}{% endblock %}-->
            <td class="show-for-medium-up">{{ student.lastAccessTime }}</td>
            <td class="no-pad">
              <div class="actions-small-1" v-if="student.id && Object.keys(student.routes).length">
                <nav>
                  <a href="#" :data-dropdown="'overflow' + student.id" class="icon-more" title="Actions"></a>
                </nav>
              </div>
            </td>
          </tr>
        </template>
        </tbody>
      </table>
    </div>

    <div v-if="mailEnabled" id="classList-email" data-reveal aria-hidden="true" class="reveal-modal small">
      <h2>Compose Email</h2>

      <ul class="meta">
        <li>Composing for <span data-relation="dynamicUsersSlot"></span></li>
      </ul>

      <fieldset class="checklist">
        <legend>Recipients</legend>
        <template v-for="group in emailGroups">
          <input :id="group.label"
                 name="groups[]"
                 type="checkbox"
                 :value="group.value"
                 v-model="group.isChecked"/>
          <label :for="group.label">{{ group.label }}</label>
        </template>
      </fieldset>

      <form ref="form" action="/mail/createMail.php" method="post" v-on:submit.prevent class="class-list-form">
        <input type="hidden" name="replyto" :value="replyTo"/>
        <input type="hidden" id="selectedIds" name="selectedIds" :value="selectedStudentExternalIds.join(',')"/>
        <input type="hidden" id="selectedGroups" name="selectedGroups" :value="selectedGroups.join(',')"/>
        <button type="submit" class="button" :disabled="!enableSendEmailButton" @click="sendEmail">{{ sendEmailButtonText }}
        </button>
      </form>
    </div>

    <div class="small-12 island" v-if="!showSkeletonLoader && !studentLists.length">
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
