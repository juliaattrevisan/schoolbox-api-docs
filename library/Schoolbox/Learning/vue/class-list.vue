<script>

export default {
  props: {
    subjectTitle: {
      type: String,
      required: true,
    },
    classCodeList: {
      type: Array,
      required: true,
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
    }
  },
  data() {
    return {
      //   subjectName: '{{ folder.name }}',
      //   subjectTitle: '{{ folder.title }}',
      //   classCode: '{{ folder.codeString }}',
      //   classCodeList: {{ folder.codes | keys | json_encode | raw }},
      // replyToEmail: '{{ replyTo }}',
      //   specialNeeds: '{{ specialNeeds }}',
      //   hideMedicalAlerts: Boolean({{ config.protect_medicalspecial }}),
      //   studentLists: [],
      //   showSkeletonLoader: true,
      //   selectedStudents: [],
      //   studentsCheckboxElements: undefined,
      //   groups: {{ emailGroups | raw }},
      //   groupsCheckboxElements: [],
      //   sendEmailButtonDefaultText: 'Compose Email',
      //   sendEmailButtonText: null,
      //   getStudentListsEndpoint: '{{ routeLookup('learning.getStudentListByClassId', {folderId: folder.getId, folder: folder}) }}',
      //   formIsReady: false,
      //   actionsPlaceholder: true,
      //   loadingText: 'Please Wait...',
      //   noEmailsFoundText: 'Sorry we cannot send an email to the selected users as they do not have an email',
      //   returnUrlName: '{{ returnUrlName }}',
      //   encodedReturnUrl: '{{ encodedReturnUrl }}',
      //   getAwardAsAchievementFormEndpoint: '{{ routeLookup('learningBadgeAward.redirectToAwardAsAchievementForm') }}',
      //   getSetAsGoalFormEndpoint: '{{ routeLookup('learningBadgeAward.redirectToSetAsGoalForm') }}',
      //   getCreateEvidenceFormEndpoint: '{{ routeLookup('learningMomentEvidence.redirectToCreateForm') }}'}
    }
  }
}
</script>
<template>
  <div class="row class-list">

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
             :class="{disabled: selectedStudentLearningMomentsEnabledIds.length === 0, 'icon-share': true}">Share Learning Moment</a>
        </li>
        <li v-if="badgesEnabled">
          <a :href="buildSetGoalUrl()" :class="{disabled: selectedStudentIds.length === 0, 'icon-goal': true}">Set Goals</a>
        </li>
        <li v-if="badgesEnabled">
          <a :href="buildAwardAchievementUrl()" :class="{disabled: selectedStudentIds.length === 0, 'icon-achievement': true}">Award Achievements</a>
        </li>
        <li v-if="pastoralEnabled">
          <a @click.prevent="addPastoralRecord"
             :class="{disabled: selectedStudentIds.length === 0, 'icon-user': true}"
          >Add {{ locale.pastoralCare.proper }} Record</a></li>
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
            <input type="checkbox" name="students[]" :id="'id-' + index" v-model="student.isChecked" />
            <label :for="'id-' + index" class="checkbox-label"></label>
          </td>
          <td>
            <img :src="'/portrait.php?id=' + student.id + '&size=square64'"
                 :alt="student.lastname + ', ' + student.firstname">
          </td>
          <td>
            <template v-if="student.id">
              <a :href="'/search/user/' + student.id">${ student.lastname + ', ' + student.firstname }</a>
            </template>
            <template v-else>
              ${ student.lastname + ', ' + student.firstname }
            </template>
            <span class="meta">(${ student.synergy_id })</span>
            <span class="show-for-print"><br>${ student.email }</span>

            {% block meta %}
            <p class="meta">
                                  <span v-if="student.EnglishSecondFlag == '1'" class="icon-eng-second-lang highlight"
                                        title="English as an Additional Language">
                                  </span>
              <span v-if="!hideMedicalAlerts && student.StudentSpecialNeedsFlag == '1'" class="icon-bell warning"
                    :title="specialNeeds">
                                  </span>
              <span v-if="!hideMedicalAlerts && student.StudentMedicalAlert != ''" class="icon-alert alert"
                    :title="'Medical Alert: ' + student.StudentMedicalAlert">
                                  </span>
            </p>
            {% endblock %}

            <ul data-dropdown-content class="f-dropdown" :id="'overflow' + student.id" v-if="student.id && Object.keys(student.routes).length">
              {% block data_dropdown_content %}
              <li v-for="element in student.routes">
                <a :href="element.href" :class="element.class" :title="element.title">${ element.label }</a>
              </li>
              {% endblock %}
            </ul>
          </td>
          <td class="show-for-large-up">${ student.yearlevel }</td>
          {% block tutor_group %}<td class="show-for-medium-up">${ student.house }</td>{% endblock %}
          {% block core_group %}{% endblock %}
          <td class="show-for-medium-up">${ student.lastAccessTime }</td>
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
      <template v-for="(group, index) in groups">
        <input :id="group.label"
               name="groups[]"
               type="checkbox"
               :value="group.value"
               v-model="group.isChecked" />
        <label :for="group.label">${ group.label }</label>
      </template>
    </fieldset>

    <form ref="form" action="/mail/createMail.php" method="post" v-on:submit.prevent class="class-list-form">
      <input type="hidden" name="replyto" :value="replyTo"/>
      <input type="hidden" id="selectedIds" name="selectedIds" :value="selectedStudentExternalIds.join(',')" />
      <input type="hidden" id="selectedGroups" name="selectedGroups" :value="selectedGroups.join(',')" />
      <button type="submit" class="button" :disabled="!enableSendEmailButton" @click="sendEmail">${ sendEmailButtonText }</button>
    </form>
  </div>

  <div class="small-12 island" v-if="! showSkeletonLoader && !studentLists.length">
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
