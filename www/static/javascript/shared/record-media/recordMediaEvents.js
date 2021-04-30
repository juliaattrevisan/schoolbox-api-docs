/*
 * Events that can occur within #record-media.vue and its children
 *
 */

export default Object.freeze({
  PROCCESS_STARTED: 'on-process-started', //usually to block/notify UI until it's stopped
  PROCCESS_STOPPED: 'on-process-stopped',
  RECORDED_MEDIA_READY: 'on-recorded-media-ready', //to notify a video can be uploaded 
  RECORDED_MEDIA_DELETED: 'on-recorded-media-deleted', //to notify a video can be uploaded 
})