/**
 * Create a Three Panel View
 *
 * @param callbackURL
 * @param context
 * @constructor
 */
function ThreePanelView(callbackURL, context) {

    this.callbackURL = callbackURL
    // default context is 'resources'
    this.context = typeof(context) === 'undefined' ? 'resources' : context

    // cache panels
    this.detailsContainer = $('#detail-window')
    this.folderDetail = $('#folder-detail')
    this.fileDetail = $('#file-detail')
    this.multiFileDetail = $('#multi-file-detail')

    // initially hide these
    this.fileDetail.hide()
    this.multiFileDetail.hide()
    this.folderDetail.hide()

    this.accessLevels = ['None', 'Read', 'Write']

}

/**
 * Resize the three panels to match window
 */
ThreePanelView.prototype.resizePanels = function() {
    $('#category-window, #file-window, #detail-window').height(
        $(window).height() - $('#header').height() - $('#footer').height() - 50
    )
}

/**
 * Retrieve metadata of folder or file(s) selected
 *
 * @param folderId
 * @param fileArray
 */
ThreePanelView.prototype.getMeta = function(folderId, fileArray) {

    // commence loading!
    this.detailsContainer.find('.loading').show()

    var threePanel = this

    // retrieve folder
    if(fileArray === undefined) {

        $.post(this.callbackURL, {folderId: folderId}, function(response) {

            var meta, mime, responseType = (typeof(response)).toLowerCase()

            if(responseType === 'object') {
                meta = response;
            }
            else if(responseType === 'string') {
                meta = JSON.parse(response);
            }
            else {
                // something went wrong and we can't parse metadata
                return
            }

            threePanel.folderDetail.find('.name').text(meta.name)

            if(meta.template) {
                threePanel.folderDetail.find('.template').html(meta.template).show()
            }
            else {
                threePanel.folderDetail.find('.template').hide()
            }

            if(meta.template) {
                mime = '/images/icons/128x128/home.png'
            }
            else {
                mime = '/images/icons/128x128/folder.png'
            }
            threePanel.folderDetail.find('img').attr('src', mime).attr('alt', meta.name)

            if(meta.type) {
                threePanel.folderDetail.find('div.group').show()
                threePanel.folderDetail.find('span.admin').text(meta.admins.name)
                threePanel.folderDetail.find('img.admin').attr('src', '/portrait.php?userId='+meta.admins.externalID+'&size=square32')
                threePanel.folderDetail.find('span.type').text(meta.type + ' Group')
                var pending_element = threePanel.folderDetail.find('.pending');
                var join_element    = threePanel.folderDetail.find('.join-btn');
                var leave_element    = threePanel.folderDetail.find('.leave-btn');
                var manage_element    = threePanel.folderDetail.find('.manage-btn');
                switch(meta.joinable) {
                    case 0: // not joinable
                        join_element.hide().parent().data('id','')
                        leave_element.hide().parent().data('id','')
                        pending_element.hide()
                        break
                    case 1: // joinable
                        join_element.show().parent().data('id', meta.id)
                        pending_element.hide()
                        leave_element.hide()
                        break
                    case 3: //already member
                        pending_element.hide()
                        join_element.hide()
                        leave_element.show().parent().data('id',meta.id)
                        break
                    case 2: // pending approval
                        join_element.hide().parent().data('id','')
                        leave_element.show().parent().data('id',meta.id)
                        pending_element.show()
                        break
                }
                //Hide manage button if not admin
                if(meta.manage) {
                    manage_element.parent().data('id',meta.id)
                }
                else {
                    manage_element.hide()
                }
            }
            else {
                threePanel.folderDetail.find('div.group').hide()
            }

            // create the list of permission to display
            var parmsTable = threePanel.folderDetail.find('table.perms')
            parmsTable.html('')

            if(typeof(meta.permissions) == "object") {
                $.each(meta.permissions, function(k, perm) {
                    parmsTable.append('<tr class="' + perm.on_off + '"><th scope="row">' + perm.group + ':</th><td>' + threePanel.accessLevels[perm.access] + '</td></tr>')
                })

                if(meta.permissions.length == 0) {
                    threePanel.folderDetail.find('h4.perms').hide()
                }
                else {
                    threePanel.folderDetail.find('h4.perms').show()
                }
            }
            else {
                threePanel.folderDetail.find('h4.perms').hide()
            }

            // list of actions to display
            var actionButtons = threePanel.folderDetail.find('.actions a')
            actionButtons.hide()
            $.each(meta.actions, function(k, v) {
                actionButtons.filter('.r'+v).show()
            })

            if(meta.actions.length == 0) {
                threePanel.folderDetail.find('h4.actions').hide()
            }
            else {
                threePanel.folderDetail.find('h4.actions').show()
            }

            threePanel.folderDetail.find('.actions').data('kind', 'folder').data('id', meta.id)

            threePanel.fileDetail.hide()
            threePanel.multiFileDetail.hide()
            threePanel.folderDetail.show()

        })
    }

    // retrieve file
    else if(fileArray instanceof Object) {

        var idList = {}, i = 0
        $(fileArray).each(function() {
            var type = $(this).hasClass('link') ? 'link' : 'file'
            if(typeof(idList[type]) == 'undefined') {
                idList[type] = []
            }
            idList[type].push($(this).attr('id').substring(5))
        })

        $.post(this.callbackURL, {folderId: folderId, files: idList}, function(response) {

            var meta, responseType = (typeof(response)).toLowerCase()

            if(responseType === 'object') {
                meta = response;
            }
            else if(responseType === 'string') {
                meta = JSON.parse(response);
            }
            else {
                // something went wrong and we can't parse metadata
                return
            }

            // show multi
            if(typeof(meta.name) == 'undefined') {

                threePanel.multiFileDetail.find('.count').text($('#file-list tr.selected').length + ' files selected')
                threePanel.multiFileDetail.find('.actions').data('id', idList)
                threePanel.multiFileDetail.find('img').attr('src', '/images/mime/128x128/multi.png')

                threePanel.fileDetail.hide()
                threePanel.folderDetail.hide()
                threePanel.multiFileDetail.show()
            }

            // show single
            else {

                threePanel.fileDetail.find('.name').text(meta.name)

                if(meta.description) {
                    threePanel.fileDetail.find('.description').html(meta.description).show()
                }
                else {
                    threePanel.fileDetail.find('.description').hide()
                }


                threePanel.fileDetail.find('.meta-who').text(meta.posted_by)
                threePanel.fileDetail.find('.meta-when').text(meta.posted_date)
                threePanel.fileDetail.find('.meta-modified-when').text(meta.modified)

                // link, display link icon
                if(meta.kind == 'link') {
                    threePanel.fileDetail.find('img').attr('src', '/images/mime/128x128/html_alt1.png').attr('alt', meta.name)
                }
                // mime not defined, bummer
                else if(typeof(meta.mime) == 'undefined') {
                    threePanel.fileDetail.find('img').attr('src', '/images/mime/128x128/mime.png').attr('alt', meta.name)
                }
                // image, display a preview
                else if(meta.mime.match(/image.*/)) {
                    var src
                    if(threePanel.context == 'eportfolio') {
                        src = '/cms/myImage.php?id='+meta.id+'&width=128&height=128'
                    }
					/* Just use an icon
					 */
                    else if(threePanel.context == 'network') {
                        src = '/images/mime/128x128/' + meta.mime;
                    }
                    else {
                        src = '/cms/image.php?id='+meta.id+'&width=128&height=128'
                    }
                    threePanel.fileDetail.find('img').attr('src', src).attr('alt', meta.name)
                }
                // file, display appropriate mime icon
                else {
                    threePanel.fileDetail.find('img').attr('src', '/images/mime/128x128/'+meta.mime).attr('alt', meta.name)
                }

                // show filesize if available
                if(typeof(meta.filesize) == 'undefined') {
                    threePanel.fileDetail.find('.filesize').text('')
                }
                else {
                    threePanel.fileDetail.find('.filesize').text('('+meta.filesize+')')
                }

                // does the hidden warning show?
                if(meta.hidden == 1) {
                    threePanel.fileDetail.find('.hidden-warning').show()
                    threePanel.fileDetail.find('img').css('opacity', '0.4')
                }
                else {
                    threePanel.fileDetail.find('.hidden-warning').hide()
                    threePanel.fileDetail.find('img').css('opacity', '1')
                }

                // list of actions to display
                var actionButtons = threePanel.fileDetail.find('.actions a')
                actionButtons.hide()
                $.each(meta.actions, function(k, v) {
                    actionButtons.filter('.r'+v).show()
                })

                if(!meta.actions || meta.actions.length == 0) {
                    threePanel.fileDetail.find('h4.actions').hide()
                }
                else {
                    threePanel.fileDetail.find('h4.actions').show()
                }

                // list of publish details
                var frm = threePanel.fileDetail.find('form.publish')
                if(meta.publish && meta.publish.length) {
                    frm.find('input[name=userId]').val(meta.publish[0].userId)
                    frm.find('input[name=fileId]').val(meta.id)

                    var sel = frm.find('select[name=pageId]').text('')
                    threePanel.fileDetail.find('form.unpublish').find('input[name=fileId]').val(meta.id)
                    $.each(meta.publish, function(i, dest) {
                        sel.append(
                            $('<option/>').val(dest.pageId + '&componentInstanceId=' + dest.componentInstanceId)
                                .text(dest.pageTitle)
                        )
                    })
                    frm.attr('action','/eportfolio/component/filelist/modifyForm.php').show()
                }
                else {
                    frm.hide()
                }

                threePanel.fileDetail.find('.actions').data('id', meta.id).data('kind', meta.kind)

                threePanel.multiFileDetail.hide()
                threePanel.folderDetail.hide()
                threePanel.fileDetail.show()
            }
        })
    }

    // loading complete!
    this.detailsContainer.find('.loading').hide()

}

ThreePanelView.prototype.actionBind = function(actions) {

    this.detailsContainer.on('click', 'a.btn', function(event) {
        var a = $(this)
        var context = a.parent()

        var kind = context.data('kind')
        var id = context.data('id')
        var action = 'f_' + a.data('action')

        // is file or link
        if(context.hasClass('rfile')) {
            if($.isFunction(actions.cms[action])) {
                actions.cms[action](kind, id)
            }
        }

        // is a folder
        else if(context.hasClass('rfolder')) {
            if($.isFunction(actions.folder[action])) {
                actions.folder[action](id)
            }
        }

        // is multifile
        else if(context.hasClass('rmultifile')) {
            if($.isFunction(actions.multi[action])) {
                actions.multi[action]()
            }
        }

    })

}