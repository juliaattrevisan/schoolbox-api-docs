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
    this.detailsContainer = $j('#detail-window')
    this.folderDetail = $j('#folder-detail')
    this.fileDetail = $j('#file-detail')
    this.multiFileDetail = $j('#multi-file-detail')

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
    $j('#category-window, #file-window, #detail-window').height(
        $j(window).height() - $j('#header').height() - $j('#footer').height() - 50
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

        $j.post(this.callbackURL, {folderId: folderId}, function(response) {

            var meta = JSON.parse(response), mime

            threePanel.folderDetail.find('.name').html(meta.name)

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
                threePanel.folderDetail.find('p.type').show()
                threePanel.folderDetail.find('span.type').html(meta.type + ' Group')
                switch(meta.joinable) {
                    case 2: // pending approval
                        threePanel.folderDetail.find('.pending').show()
                        // no break
                    case 0: // not joinable
                        threePanel.folderDetail.find('.join-btn').hide().parent().data('id','')
                        break
                    case 1: // joinable
                        threePanel.folderDetail.find('.join-btn').show().parent().data('id', meta.id)
                        threePanel.folderDetail.find('.pending').hide()
                        break
                }
            }
            else {
                threePanel.folderDetail.find('p.type').hide()
            }

            // create the list of permission to display
            var permsUL = threePanel.folderDetail.find('ul.perms')
            permsUL.html('')

            if(typeof(meta.permissions) == "object") {
                $j.each(meta.permissions, function(k, perm) {
                    permsUL.append('<li><b>' + perm.group + '</b>: ' + threePanel.accessLevels[perm.access] + '</li>')
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
            $j.each(meta.actions, function(k, v) {
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
        $j(fileArray).each(function() {
            var type = $j(this).hasClass('link') ? 'link' : 'file'
            if(typeof(idList[type]) == 'undefined') {
                idList[type] = []
            }
            idList[type].push($j(this).attr('id').substring(5))
        })

        $j.post(this.callbackURL, {folderId: folderId, files: idList}, function(response) {

            var meta = JSON.parse(response)

            // show multi
            if(typeof(meta.name) == 'undefined') {

                threePanel.multiFileDetail.find('.count').html($j('#file-list tr.selected').length + ' files selected')
                threePanel.multiFileDetail.find('.actions').data('id', idList)
                threePanel.multiFileDetail.find('img').attr('src', '/images/mime/128x128/multi.png')

                threePanel.fileDetail.hide()
                threePanel.folderDetail.hide()
                threePanel.multiFileDetail.show()
            }

            // show single
            else {

                threePanel.fileDetail.find('.name').html(meta.name)

                if(meta.description) {
                    threePanel.fileDetail.find('.description').html(meta.description).show()
                }
                else {
                    threePanel.fileDetail.find('.description').hide()
                }


                threePanel.fileDetail.find('.meta-who').html(meta.posted_by)
                threePanel.fileDetail.find('.meta-when').html(meta.posted_date)

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
                    if(threePanel.context = 'eportfolio') {
                        src = '/cms/sendMyFile.php?id='+meta.id+'&width=128&height=128'
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
                    threePanel.fileDetail.find('.filesize').html('')
                }
                else {
                    threePanel.fileDetail.find('.filesize').html('('+meta.filesize+')')
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
                $j.each(meta.actions, function(k, v) {
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

                    var sel = frm.find('select[name=pageId]').html('')
                    threePanel.fileDetail.find('form.unpublish').find('input[name=fileId]').val(meta.id)
                    $j.each(meta.publish, function(i, dest) {
                        sel.append(
                            $j('<option/>').val(dest.pageId + '&componentInstanceId=' + dest.componentInstanceId)
                                .html('ePortfolio/'+dest.pageTitle)
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

    // note: if jquery ever gets updated, .delegate() will probably need to be replaced with .on()
    this.detailsContainer.delegate('a.btn', 'click', function(event) {
      console.log(this)

        var a = $j(this)
        var context = a.parent()

        var kind = context.data('kind')
        var id = context.data('id')
        var action = a.data('action')

        // is file or link
        if(context.hasClass('rfile')) {
            if($j.isFunction(actions.cms[action])) {
                actions.cms[action](kind, id)
            }
        }

        // is a folder
        else if(context.hasClass('rfolder')) {
            if($j.isFunction(actions.folder[action])) {
                actions.folder[action](id)
            }
        }

        // is multifile
        else if(action == 'delete') {
            actions.multi[action]()
        }

    })

}