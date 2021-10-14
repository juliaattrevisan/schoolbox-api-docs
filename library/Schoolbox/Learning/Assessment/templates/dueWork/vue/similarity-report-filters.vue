<script>

/**
 * Contains the similarity report filters, score and status
 */



export default {

  methods: {
    
  }

}
</script>

<template>

  <div class="content">
    <div class="small-12 medium-6 columns">
      <legend>Similarity score</legend>
        <select>
          <option value="">-</option>
          <option value="">&gt;= 40%</option>
          <option value="">50%</option>
          <option value="">60%</option>
          <option value="">70%</option>
          <option value="">80%</option>
          <option value="">90%</option>
        </select>
    </div>
    <div class="small-12 medium-6 columns">
      <fieldset class="checklist">
        <legend>Status</legend>
        <ul class="option-list">
          <li>
            <input type="checkbox" id="resubmission-required" value="resubmission-required">
            <label for="resubmission-required">Resubmission required</label>
          </li>
          <li>
            <input type="checkbox" id="marking-required" value="marking-required">
            <label for="marking-required">Marking required</label>
          </li>
          <li>
            <input type="checkbox" id="reviewed" value="reviewed">
            <label for="reviewed">Reviewed</label>
          </li>
        </ul>
      </fieldset>
    </div>
  </div>

</template>

<style lang="scss" scoped>


</style>