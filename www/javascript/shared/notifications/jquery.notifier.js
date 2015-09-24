/* Implmentation of Schoolbox Websocket Notifications */

(function($) {

    $.fn.notifier = function(options) {
        var settings = $.extend({

            // selectors

            counter:    '#unread-count',
            content:    '#msg-content',
            list:       '#message-list',
            readall:    '#msg-mar',
            alertBox:   '#alert-dialog',
            alertList:  '#alert-content',
            footer:     '[data-msgfooter-action]',
            modalTitle: '[data-modal-title]',

            // socket configuration

            secure:    'false',
            host:      '192.168.1.1',
            port:      '8080',
            path:      '/',
            prefetch:  5,
            maxstack:  3,
            debug:     false,
            domwait:   1000,
            transport: 'socket',
            timeout:   500,
            maxRetry:  10,
            alertTtl:  30,

            // data attributes

            counterUnread: 'unread',
            tplItem:      'msgitem',
            tplItemHref:  'msgitem-href',
            tplItemThumb: 'msgitem-thumb',
            tplItemTitle: 'msgitem-title',
            tplItemTime:  'msgitem-time',
            tplItemAck:   'msgitem-ack',

            // internal configuration

            itemUnread:       'unread',
            itemTimeFormat:   'LLLL',
            itemTextEmphasis: 'b',

            // dictionary values

            dictItemOthers:       'others',
            dictItemAnd:          'and',
            dictFooterSeeAll:     'See All',
            dictFooterNoMessages: 'No Messages'

        }, options);

        var backoff = 0;

        // cast boolean from potential string
        settings.secure = (settings.secure === 'true' || settings.secure === true);

        return this.each(function() {

            // detect websocket support
            var support = "MozWebSocket" in window ? 'MozWebSocket' : ("WebSocket" in window ? 'WebSocket' : null);
            if (support == null || settings.transport == 'poll') {
                // falling back to long poll
                support = "LongPoll";
                window["LongPoll"] = LongPoll;
                if(settings.debug) { console.warn('Websockets are not supported, falling back to polling'); }
            }

            /**
             * Establish a connection via websocket
             * @private
             */
            function establishConnection() {
                conn = new window[support](url);
                conn.onopen = onOpen;
                conn.onerror = onError;
                conn.onmessage = onMessage;
                conn.onclose = onClose;

                if(settings.debug) { console.log('Created new socket connection'); }
            }

            function pollReconnect() {
                var jitter;

                if(attemptReconnect && backoff < settings.maxRetry) {
                    jitter = parseInt(Math.random() * Math.min(settings.timeout * settings.maxRetry, Math.pow(2, backoff++) * settings.timeout));

                    if(settings.debug) { console.log('Polling for connection, attempt #'+backoff+', waiting '+jitter+'ms'); }

                    setTimeout(establishConnection, jitter);

                } else {
                    if(settings.debug) { console.log('Poll timeout exceeded. Giving up.'); }
                    $(settings.list).children('.footer').children('a').html('Could not connect');
                }
            }

            /**
             * @private
             * @param data
             */
            function send(data) {
                if(conn) {
                    if(settings.debug) { console.log('Sending data to server'); }
                    conn.send(JSON.stringify(data));
                }
                else {
                    if(settings.debug) { console.log('No connection: not sending data'); }
                }
            }

            /**
             * @private
             */
            function updateDOM() {

                if(settings.debug) { console.log('Updating DOM'); }

                var dom = $(self);

                // do the following on every DOM update request

                // replicate the list and delete its contents
                var content = $(settings.content).clone(false).empty(),// $('<ul/>').attr('id', 'messages'),
                    toInsert = [],
                    id;

                for(id in messages) {
                    // insert into temp array
                    messages[id].id = id;
                    toInsert.push(messages[id]);
                }

                toInsert.sort(function(a,b) {
                    // sort ascending by timestamp (we'll iterate descending)
                    return (a.timestamp == b.timestamp ? 0 : (a.timestamp < b.timestamp ? -1 : 1));
                });

                var i;

                var footerText = '';

                if(toInsert.length > 0) {
                    // append the list of messages
                    for(i = toInsert.length - 1; i >= 0; --i) {

                        var li = settings.tpl.clone();

                        applyMessageData(li, toInsert[i]);

                        content.append(li);
                    }
                    footerText = settings.dictFooterSeeAll;
                }
                else {
                    footerText = settings.dictFooterNoMessages;
                }

                // update the actual DOM
                $(settings.list).find(settings.footer).html(footerText);
                $(settings.content).replaceWith(content)/*.foundation('equalizer')*/;

                // update the counter (duplicate selector intentional)
                var unreadTotal = $(settings.content).find('[data-status="' + settings.itemUnread + '"]').length;

                // set the counter
                $('[data-' + settings.counterUnread + ']').attr('data-' + settings.counterUnread, unreadTotal);

                $(settings.counter).html(unreadTotal ? unreadTotal : '');

                $.ajax({
                    accepts  : 'application/json',
                    async    : true,
                    type     : 'POST',
                    url      : '/ajax/msgcounter.php',
                    data     : {c : unreadTotal}
                });

                clearInterval(ii);
                ii = setInterval(updateRelativeTimes, 9000);
            }

            /**
             * @private
             */
            function updateRelativeTimes() {
                // don't render a future time if server clock happens to be ahead of client
                var now = Math.floor(new Date().getTime() / 1000);

                $(settings.list).children('[data-' + settings.tplItem + ']').each(function(k, v) {
                    var msgId = this.id.split('-')[1];
                    if(typeof messages[msgId] !== 'undefined') {
                        var timestamp = messages[msgId].timestamp;
                        $(this).find('[data-' + settings.tplItemTime + ']').text(moment.unix(Math.min(timestamp, now)).fromNow());
                    }
                });
            }


            /**
             * @private
             * @param {string} string
             * @param {object} tokens
             */
            function applyTokens(string, tokens) {
                var b  = '<'  + settings.itemTextEmphasis + '>',
                    _b = '</' + settings.itemTextEmphasis + '>';

                // don't blow up if tokens is undefined
                $.each(tokens || {}, function(token, value) {

                    // arrange instance into an array so they can be sorted
                    var instances = [];

                    // insert instances into temp array to allow sorting by time
                    var time;
                    for(time in value) {
                        var v = value[time];
                        v.time = time;
                        instances.push(v);
                    }

                    instances.sort(function(a,b) {
                        // sort descending by timestamp (which will give highest precedence to most recent)
                        return (a.time == b.time ? 0 : (a.time < b.time ? 1 : -1));
                    });

                    var i, stack = [];
                    for(i = 0; i < instances.length; ++i) {
                        if(instances[i].mode < 0) {
                            if(i == 0) {
                                stack = [instances[i].text];
                            }
                        }
                        else if(instances[i].mode > 0) {
                            stack.push(instances[i].text);
                        }
                        else {
                            stack = [instances[i].text];
                        }
                    }

                    if(stack.length > settings.maxstack) {
                        // trim
                        var excess = stack.length - settings.maxstack + 1;
                        stack = [stack[0], stack[1], excess + ' ' + settings.dictItemOthers];
                    }

                    string = string.replace(':'+token, b + stack.join(_b + ', ' + b) + _b);
                });
                return string.replaceLast(_b + ',', b + ' ' + settings.dictItemAnd);
            }


            /**
             * @private
             * @param {jQuery} element
             * @param {object} data;   defined as [id, href, timestamp, status, icon, body(, params)]
             */
            function applyMessageData(element, data) {

                var now = moment.unix(data.timestamp),
                    msg = applyTokens(data.body, data.params);

                element.attr('id', 'm-' + data.id).attr('data-status', data.status).addClass('status-' + data.status);

                element.find('[data-' + settings.tplItemHref + ']').attr('href', data.href);
                element.find('[data-' + settings.tplItemTitle + ']').html(msg);
                element.find('[data-' + settings.tplItemTime + ']').html(now.fromNow()).attr('title', now.format(settings.itemTimeFormat));
                element.find('[data-' + settings.tplItemThumb + ']').attr('src', data.icon);
            }


            /**
             * Socket Event
             * @private
             */
            var onOpen = function() {
                if(settings.debug) { console.log('onOpen() triggered'); }

                // reset backoff counter
                backoff = 0;

                // initiate handshake
                send({subscribe : true});

                // update UI to reflect connection success
                $(self).addClass('socket connected').data('connected', true);
                $(settings.counter).attr('title', 'View Messages');
            }

            /**
             * Socket Event
             * @private
             * @param e
             */
            var onError = function(e) {
                if(settings.debug) { console.log('onError() triggered'); }
                $(self).removeClass('connected').data('connected', false);
                var data;
                try {
                    data = JSON.parse(e.data)
                }
                catch(e) {
                    data = e.data;
                }

                // TODO use this somehow
            }

            /**
             * Socket Event
             * @private
             * @param e
             */
            var onMessage = function(e) {
                if(settings.debug) { console.log('onMessage() triggered'); }
                var data;
                try {
                    data = JSON.parse(e.data)

                    // is there a better way to do this? there may be multiple commands within a single message
                    if(data.subscribe) { self.pipeSubscribe(data.subscribe); }
                    if(data.fetch)     { self.pipeFetch(data.fetch); }
                    if(data.push)      { self.pipePush(data.push); }
                    if(data.ack)       { self.pipeMarkAsRead(data.ack); }
                    if(data.alert)     { self.pipeAlert(data.alert); }
                    if(data.error)     { self.pipeError(data); }
                }
                catch(err) {
                    data = e.data;
                }

                // TODO use this somehow
            }

            /**
             * Socket Event
             * @private
             * @param e
             */
            var onClose = function(e) {
                if(settings.debug) { console.log('onClose() triggered'); }
                $(self).removeClass('connected').data('connected', false);
                pollReconnect();
            }

            /**
             * Stores notification instances in a stacked fashion
             *
             * @private
             * @param msgId
             * @param amqpMsg
             * @param mode
             */
            var storeMessage = function(msgId, amqpMsg, mode) {
                // if no message set for this ID, just set it
                if(typeof messages[msgId] == 'undefined') {
                    // not previously set, just insert
                    messages[msgId] = amqpMsg;
                    return;
                }

                // check the titles match; discard the new one if they don't
                if(messages[msgId].body != amqpMsg.body) {
                    if(settings.debug) { console.log('Messages with matching IDs have conflicting titles'); }
                    return;
                }

                // get the existing params and status
                var params = messages[msgId].params;
                var status = messages[msgId].status;

                // override the base message data if newer
                if(amqpMsg.timestamp >= messages[msgId].timestamp) {
                    messages[msgId] = amqpMsg;
                }

                // deal with param stacking
                $.each(amqpMsg.params, function(token, paramSet) {
                    $.each(paramSet, function(hash, param) {
                        switch(param.mode) {
                            case -1:
                                // IGNORE: do nothing
                                break;
                            case 0:
                                // REPLACE: overwrite
                                params[token] = {};
                                params[token][hash] = param;
                                break;
                            case 1:
                                // APPEND: stack
                                params[token][hash] = param;
                                break;
                        }
                    });
                });

                // reinsert revised param list
                messages[msgId].params = params;

                // determine status (if push, allow override; if not, preserve previous)
                messages[msgId].status = mode == 'push' ? amqpMsg.status : status;
            }

            /**
             * Response Event
             * @privileged
             * @param data
             */
            this.pipeSubscribe = function(data) {
                if(settings.debug) { console.log('Message from server: parsing subscribe'); }
                // don't try a fetch if the subscription came back false
                if(data === true) {
                    $(self).data('subscribed', true);
                    send({fetch : settings.prefetch});
                }
            }

            /**
             * Response Event
             * @privileged
             * @param data
             */
            this.pipeFetch = function(data) {
                if(settings.debug) { console.log('Message from server: parsing fetch'); }
                $.each(data, function(msgId, amqpMsg) {
                    storeMessage(msgId, amqpMsg, 'fetch');
                });
                $(self).data('message-count', messages.length);
                clearTimeout(domTimeout);
                domTimeout = setTimeout(updateDOM, settings.domwait);
            }

            /**
             * Response Event
             * @privileged
             * @param data
             */
            this.pipePush = function(data) {
                if(settings.debug) { console.log('Message from server: parsing push'); }
                $.each(data, function(msgId, amqpMsg) {
                    storeMessage(msgId, amqpMsg, 'push');
                });
                $(self).data('message-count', messages.length);
                clearTimeout(domTimeout);
                domTimeout = setTimeout(updateDOM, settings.domwait);
            }

            /**
             * Response Event
             * @privileged
             * @param data
             */
            this.pipeMarkAsRead = function(data) {
                if(settings.debug) { console.log('Message from server: parsing mark_as_read'); }
                $.each(data, function(i, msgId) {
                    // if we haven't seen this message, ignore this notification
                    if(typeof messages[msgId] === 'undefined') {
                        // jQuery loop equiv for "continue"
                        return true;
                    }

                    messages[msgId].status = 'read';

                    return true;
                });
                updateDOM();
            }

            this.pipeAlert = function (data) {
                if(settings.debug) { console.log('Message from server: parsing alert'); }
                // while there will always only be one, it follows the same data structure as the others
                $.each(data, function (msgId, amqpMsg) {

                    var ii,
                        box         = $(settings.alertBox),
                        title       = box.find(settings.modalTitle),
                        list        = $(settings.alertList),
                        item        = settings.alertTpl.clone(false),
                        noModal     = typeof box.data('ui-dialog') == 'undefined',
                        closeDialog = function () {
                            box.foundation('reveal', 'close');
                            clearTimeout(ii);
                        };

                    // jam that data back in there!
                    amqpMsg.id = msgId;

                    applyMessageData(item, amqpMsg);

                    list.append(item);

                    if (!title.html().length) {
                        title.text(amqpMsg.meta.title);
                    }

                    $(box).foundation('reveal', 'open');

                    if (settings.alertOpen) {
                        // reset the countdown
                        clearTimeout(ii);
                    }

                    ii = setTimeout(closeDialog, (amqpMsg.meta.ttl || settings.alertTtl) * 1000);

                    // jQuery loop equiv for "break"
                    return false;
                });
            }

            /**
             * Response Event
             * @privileged
             * @param data
             */
            this.pipeError = function(data) {
                if(settings.debug) { console.log('Message from server: parsing error'); }
                $(self).data('connected', false);
                switch(data.code) {
                    case 401:
                        console.warn('Either no session, or does not match parent.');
                        attemptReconnect = false;
                        break;
                    case 503:
                        console.warn('Service is unavailable: ' + !!data.retry ? 'cannot retry' : 'can retry');
                        attemptReconnect = !!data.retry;
                        break;
                }
                conn.close();
            }


            /* constructor method execution starts here */
            if(settings.debug) { console.log('Constructing Notifier on', this); }

            var self = this;
            var url = (settings.secure ? 'wss' : 'ws')
                + '://'
                + settings.host
                + ':'
                + settings.port
                + settings.path;
            var conn;
            var messages = {};
            var attemptReconnect = true;
            var domTimeout;
            var ii;

            // yank the template content out of their containers
            settings.tpl      = $(settings.content).find('[data-' + settings.tplItem + ']').clone(false);
            settings.alertTpl = $(settings.alertList).find('[data-' + settings.tplItem + ']').clone(false);
            $(settings.content).empty();
            $(settings.alertList).empty();

            // whenever the flyout is about to open, update relative times so they appear correctly
            $(settings.counter).on('click', function(e) {
                e.preventDefault();
                updateRelativeTimes();
            });

            // mark-as-read listener
            $(settings.list).on('click', 'a', function(e) {

                var $a     = $(this);
                var $item  = $a.closest('[data-' + settings.tplItem + ']');
                var pip    = $a.dataIsset(settings.tplItemAck);
                var action = $a.dataIsset(settings.tplItemHref) && $item.dataIsset(settings.itemUnread);

                if (pip || action) {
                    var msgId = $item.attr('id').split('-')[1];
                    send({ack : msgId});

                    if(pip) {
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        return false;
                    }
                }
                return true;
            });


            // mark-ALL-as-read listener
            $(settings.readall).on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                var unreads = [];
                $(settings.content).find('[data-status="' + settings.itemUnread + '"]').each(function() {
                    unreads.push(this.id.substr(2));
                });

                send({ack : unreads});
                return false;
            });

            // listen for alert open/close; we bind globally, but ignore anything not relevant to us
            $(document).on('open.fndtn.reveal close.fndtn.reveal', '[data-reveal]', function (e) {
                if ($(e.target).is($(settings.alertBox))) {
                    settings.alertOpen = e.type == 'open';
                }
            });

            // once the alert box has finished closing, clear it out for next use
            $(document).on('closed.fndtn.reveal', '[data-reveal]', function (e) {
                var target = $(e.target),
                    box    = $(settings.alertBox);

                // remove the content of the alert box
                if (target.is(box)) {
                    box.find(settings.modalTitle).empty();
                    $(settings.alertList).empty();
                }
            });

            // attempt a connection
            establishConnection();
        });
    }

    function LongPoll() {
        this.i = 30000;
        // perform first check, then every 30 seconds thereafter
        this.check.apply(this);
        this.ii = setInterval(this.check.bind(this), this.i);
        this.count = 0;
    }

    LongPoll.prototype = {

        check     : function() {
            var self = this;
            // request an update
            $.ajax({
                accepts  : 'application/json',
                async    : true,
                type     : 'GET',
                url      : '/ajax/messages.php',
                dataType : 'json',
                complete : function(xhr, status) {
                    self.onmessage({ data : xhr.responseText });
                }
            });
            this.count++;
            // only allow 30 minutes of polling
            if(this.count > 60) {
                this.close();
            }
        },

        send      : function(data) {
            var self = this;
            // send an ack
            $.ajax({
                accept   : 'application/json',
                async    : true,
                type     : 'POST',
                url      : '/ajax/messages.php',
                dataType : 'json',
                data     : {data : data},
                complete : function(xhr, status) {
                    self.onmessage({ data : xhr.responseText });
                }
            });
        },

        close     : function(e) {
            clearInterval(this.ii);
        },

        // stubs to be overridden
        onmessage : function(e) {},
        onopen    : function(e) {},
        onclose   : function(e) {}
    };


    // declare string functions (courtesy of http://stackoverflow.com/a/2729694/1877343)
    if(!String.prototype.reverse) {
        String.prototype.reverse = function () {
            return this.split('').reverse().join('');
        };
    }

    if(!String.prototype.replaceLast) {
        String.prototype.replaceLast = function (what, replacement) {
            return this.reverse().replace(new RegExp(what.reverse()), replacement.reverse()).reverse();
        };
    }

    if(!$.fn.dataIsset) {
        $.fn.dataIsset = function (data) {
            return this.each(function () {
                return ('undefined' != typeof $(this).data(data));
            });
        }
    }

} (jQuery));
