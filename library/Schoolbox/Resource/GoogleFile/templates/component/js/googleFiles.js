/**
 * To select a file from the Google Drive picker, then step through the file edit permissions, file comment permissions, and lastly file processing 
 */

import { gsap } from 'gsap/all';

    
const googleFiles = document.getElementById('googleFiles');
const clientId = googleFiles.dataset.clientId; 
const levels = googleFiles.dataset.levels; 
const saveRoute = googleFiles.dataset.saveRoute; 
const componentId = googleFiles.dataset.componentId; 
const folderId = googleFiles.dataset.folderId; 

const $gModal = $('#gfile');
const $step0 = $('#gfile-step0');
const $step1 = $('#gfile-step1');
const $step2 = $('#gfile-step2');
const $step3 = $('#gfile-step3');

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
            $gModal.css( { overflowX: 'hidden' } ); //to avoid the $step element from being shown outside of the modal bounds
            $step.css( { display: 'block' } );
        },
        onComplete: () => {
            $gModal.css( { overflowX: 'visible' } );
        } 
    } );
}

$('#find-files').on('click', function () {
    $step0.css( { display: 'block' } );
    $step1.css( { display: 'none' } );
    $step2.css( { display: 'none' } );
    $step3.css( { display: 'none' } );
});

$('#find-files').googlePicker({
    clientId: clientId,
    containerID: '#gfile-step0',
    modules: ['client:auth2', 'picker'],
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
        $step0.css( { display: 'none' } );
        showNextStep($step1);
        $('#gfile-google-ids').val(JSON.stringify(selected));
    }
});

function clearSelections () {
    $('[data-gfile-edit]').each(function () {
        $(this).prop('checked', false);
        $(this).prop('disabled', false);
    });
    $('[data-gfile-comment]').each(function () {
        $(this).prop('checked', false);
        $(this).prop('disabled', false);
    });
    $step1.css( { display: 'block' } );
    $step2.css( { display: 'none' } );
    $step3.css( { display: 'none' } );
}

$step1.on('click', '[data-gfile-edit]', function (e) {
    $('[data-action="continue"]').removeAttr('disabled');
});
$step1.on('click', 'a[data-action]', function (e) {
    e.preventDefault();
    $step1.css( { display: 'none' } );
    switch ($(this).data('action')) {
        case 'cancel':
            clearSelections();
            $gModal.foundation('reveal', 'close');
            return;
        case 'continue':
            let selected = $('input[name="gfileEdit"]:checked').val();
            $('[data-gfile-comment]').each(function () {
                if ($(this).val() > selected) {
                    $(this).attr('disabled', true);
                }
                // if selected edit level = 1, there will be only one option available.
                // Pre-check it to save one extra click
                if (selected == levels && $(this).val() == selected) {
                    $(this).prop('checked', true);
                    $('[data-action="share"]').removeAttr('disabled');
                }
            });
            showNextStep($step2);
            break;
    }
});
$step2.on('click', '[data-gfile-comment]', function (e) {
    $('[data-action="share"]').removeAttr('disabled');
});
$step2.on('click', 'a[data-action]', function (e) {
    e.preventDefault();
    switch ($(this).data('action')) {
        case 'cancel':
            $gModal.foundation('reveal', 'close');
            clearSelections();
            return;
        case 'share':
            $step2.css( { display: 'none' } );
            showNextStep($step3);
            $.ajax({
                type: 'POST',
                url: saveRoute,
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
$(`#component${componentId} [data-action="delete"]`).on('click', function (event) {
    event.preventDefault();
    $(this).confirmDialog({
        text: 'Are you sure you want to delete this google file?',
        confirmLabel: 'Delete',
        confirmEvent: function () {
            $.ajax({
                url: $(event.target).attr('href'),
                type: 'DELETE',
                success: function (result) {
                    window.location.href = `/homepage/${folderId}`;
                },
            });

        }
    });
});
$(`#component${componentId} [data-action="permission"]`).googlePicker({
    clientId: clientId,
    modules: ['client:auth2'],
    onAuth: function () {
        $('[data-action="continue"]').removeAttr('disabled');
        $('[data-action="share"]').removeAttr('disabled');
        $gModal.foundation('reveal', 'open');
        $step0.css( { display: 'none' } );
        $step1.css( { display: 'block' } );
        $step2.css( { display: 'none' } );
        $step3.css( { display: 'none' } );
    },
});

$(`#component${componentId} [data-action="permission"]`).on('click', function (event) {
    const editLevel = $(event.target).data('edit-level'); 
    const commentLevel = $(event.target).data('comment-level');
    $('#gfile-edit-' + editLevel).prop('checked', true);
    $('#gfile-comment-' + commentLevel).prop('checked', true);
    $('#gfile-google-ids').val(JSON.stringify([{'id': $(event.target).data('id')}]));
});
