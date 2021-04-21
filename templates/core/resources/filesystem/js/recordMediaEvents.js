/*
 * Events that can occur within #record-media.vue and its children
 *
 */

export default Object.freeze({
  PROCCESS_STARTED: 'PROCCESS_STARTED', //usually to block/notify UI until it's stopped
  PROCCESS_STOPPED: 'PROCCESS_STOPPED'
})