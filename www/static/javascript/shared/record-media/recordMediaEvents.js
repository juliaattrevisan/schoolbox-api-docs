/*
 * Events that can occur within #record-media.vue and its children
 *
 */

export default Object.freeze({
  PROCCESS_STARTED: 'on-process-started', //usually to block/notify UI until it's stopped
  PROCCESS_STOPPED: 'on-process-stopped',
  VIDEO_READY: 'on-video-ready' //to safely access an initiated videojs instance
})