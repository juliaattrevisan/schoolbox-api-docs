/**
 * To select a file from the Google Drive picker, then step through the file edit permissions, file comment permissions, and lastly file processing
 */

import { gsap } from 'gsap/all';

window.gfGlobals = {};

$(document).ready(
   function () {
       const googleFiles = document.getElementById('googleFiles');

       gfGlobals.levels = googleFiles.dataset.levels;
       gfGlobals.saveRoute = googleFiles.dataset.saveRoute;
       gfGlobals.componentId = googleFiles.dataset.componentId;
       gfGlobals.folderId = googleFiles.dataset.folderId;

       gfGlobals.$gModal = $('#gfile');
       gfGlobals.$step0 = $('#gfile-step0');
       gfGlobals.$step1 = $('#gfile-step1');
       gfGlobals.$step2 = $('#gfile-step2');
       gfGlobals.$step3 = $('#gfile-step3');

       $('#find-files').on('click', function () {
           gfGlobals.$step0.css( { display: 'block' } );
           gfGlobals.$step1.css( { display: 'none' } );
           gfGlobals.$step2.css( { display: 'none' } );
           gfGlobals.$step3.css( { display: 'none' } );
       });

       $('#find-files').googlePicker({
           clientId: $('#googleFiles').attr('data-client-id'),
           googleAccessToken: window.googleAccessToken,
           googleAccessTokenType: window.googleAccessTokenType,
           mimeTypes: [
               'application/msword',
               'application/vnd.ms-powerpoint',
               'application/vnd.ms-excel',
               'application/vnd.google-apps.document',
               'application/vnd.google-apps.presentation',
               'application/vnd.google-apps.spreadsheet'
           ],
           onSelect: function (docs) {
               let selected = docs.map(function (v) {
                   return {
                       'id': v.id,
                       'name': v.name,
                       'icon': v.iconUrl
                   }
               });
               gfGlobals.$step0.css( { display: 'none' } );
               showNextStep(gfGlobals.$step1);
               $('#gfile-google-ids').val(JSON.stringify(selected));
           }
       });

       gfGlobals.$step1.on('click', '[data-gfile-edit]', function (e) {
           $('[data-action="continue"]').removeAttr('disabled');
       });

       gfGlobals.$step1.on('click', 'a[data-action]', function (e) {
           e.preventDefault();
           gfGlobals.$step1.css( { display: 'none' } );
           switch ($(this).data('action')) {
               case 'cancel':
                   clearSelections();
                   gfGlobals.$gModal.foundation('reveal', 'close');
                   return;
               case 'continue':
                   let selected = $('input[name="gfileEdit"]:checked').val();
                   $('[data-gfile-comment]').each(function () {
                       if ($(this).val() > selected) {
                           $(this).attr('disabled', true);
                       }
                       // if selected edit level = 1, there will be only one option available.
                       // Pre-check it to save one extra click
                       if (selected == gfGlobals.levels && $(this).val() == selected) {
                           $(this).prop('checked', true);
                           $('[data-action="share"]').removeAttr('disabled');
                       }
                   });
                   showNextStep(gfGlobals.$step2);
                   break;
           }
       });

       gfGlobals.$step2.on('click', '[data-gfile-comment]', function (e) {
           $('[data-action="share"]').removeAttr('disabled');
       });

       gfGlobals.$step2.on('click', 'a[data-action]', function (e) {
           e.preventDefault();
           switch ($(this).data('action')) {
               case 'cancel':
                   gfGlobals.$gModal.foundation('reveal', 'close');
                   clearSelections();
                   return;
               case 'share':
                   gfGlobals.$step2.css( { display: 'none' } );
                   showNextStep(gfGlobals.$step3);
                   $.ajax({
                       type: 'POST',
                       url: gfGlobals.saveRoute,
                       data: {
                           googleIds: $('#gfile-google-ids').val(),
                           gfileEdit: $('input[name="gfileEdit"]:checked').val(),
                           gfileComment: $('input[name="gfileComment"]:checked').val(),
                           accessToken: $('#gfile-access-token').val(),
                           tokenType: $('#gfile-token-type').val(),
                       },
                       success: function (data) {
                           window.location.reload();
                       }
                   });
                   break;
           }
       });

       $(`#component${gfGlobals.componentId} [data-action="delete"]`).on('click', function (event) {
           event.preventDefault();
           $(this).confirmDialog({
               googleAccessToken: window.googleAccessToken,
               googleAccessTokenType: window.googleAccessTokenType,
               text: 'Are you sure you want to delete this google file?',
               confirmLabel: 'Delete',
               confirmEvent: function () {
                   $.ajax({
                       url: $(event.target).attr('href'),
                       type: 'DELETE',
                       success: function (result) {
                           window.location.href = `/homepage/${gfGlobals.folderId}`;
                       },
                   });
               }
           });
       });

       $(`#component${gfGlobals.componentId} [data-action="permission"]`).googlePicker({
           clientId: $('#googleFiles').attr('data-client-id'),
           googleAccessToken: window.googleAccessToken,
           googleAccessTokenType: window.googleAccessTokenType,
           onAuth: function () {
               $('[data-action="continue"]').removeAttr('disabled');
               $('[data-action="share"]').removeAttr('disabled');
               gfGlobals.$gModal.foundation('reveal', 'open');
               gfGlobals.$step0.css( { display: 'none' } );
               gfGlobals.$step1.css( { display: 'block' } );
               gfGlobals.$step2.css( { display: 'none' } );
               gfGlobals.$step3.css( { display: 'none' } );
           },
       });

       $(`#component${gfGlobals.componentId} [data-action="permission"]`).on('click', function (event) {
           const editLevel = $(event.target).data('edit-level');
           const commentLevel = $(event.target).data('comment-level');
           $('#gfile-edit-' + editLevel).prop('checked', true);
           $('#gfile-comment-' + commentLevel).prop('checked', true);
           $('#gfile-google-ids').val(JSON.stringify([{'id': $(event.target).data('id')}]));
       });
   }
);

/**
 * Animate the next step from %25 across: https://greensock.com/docs/v3/GSAP/gsap.from()
 *
 * @param { JQuery Element }
 */
function showNextStep ($step) {
    gsap.from($step, {
        x: '25%',
        alpha: 0,
        duration: 0.3,
        onStart: () => {
            gfGlobals.$gModal.css( { overflowX: 'hidden' } ); //to avoid the $step element from being shown outside of the modal bounds
            $step.css( { display: 'block' } );
        },
        onComplete: () => {
            gfGlobals.$gModal.css( { overflowX: 'visible' } );
        }
    } );
}

function clearSelections () {
    $('[data-gfile-edit]').each(function () {
        $(this).prop('checked', false);
        $(this).prop('disabled', false);
    });
    $('[data-gfile-comment]').each(function () {
        $(this).prop('checked', false);
        $(this).prop('disabled', false);
    });
    gfGlobals.$step1.css( { display: 'block' } );
    gfGlobals.$step2.css( { display: 'none' } );
    gfGlobals.$step3.css( { display: 'none' } );
}
