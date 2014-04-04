/* Implmentation of Schoolbox Websocket Notifications */

(function($) {

    $.fn.notifier = function(options) {
        var settings = $.extend({
            secure:    'false',
            host:      '192.168.1.1',
            port:      '8080',
            sslport:   '8081',
            path:      '',

            counter:   '#go-msg',
            content:   '#msg-content',
            list:      '#message-list',

            prefetch:  5,
            debug:     false,
            domwait:   1000
        }, options);

        // cast boolean from potential string
        settings.secure = (settings.secure === 'true' || settings.secure === true);

        return this.each(function() {

            // detect websocket support
            var support = "MozWebSocket" in window ? 'MozWebSocket' : ("WebSocket" in window ? 'WebSocket' : null);
            if (support == null) {
                // TODO fallback to flash or polling
                if(settings.debug) { console.error('Websockets are not supported'); }
                return;
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
                if(attemptReconnect) {
                    if(settings.debug) { console.log('Polling for connection'); }
                    setTimeout(establishConnection, 2000);
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

                // template for message list items
                var msgLiTpl = $('<li/>').addClass('msg').append(
                    $('<a/>').addClass('action').attr('href', '#').append(
                        $('<img/>').attr('width', '32').attr('height', '32')
                    ).append(
                        $('<span/>').addClass('descript')
                    ).append(
                        $('<time/>').addClass('time')
                    )
                ).append(
                    $('<a/>').addClass('pip').attr('href', '#').attr('title', 'Mark as read')
                );

                var footerText = '';

                if(toInsert.length > 0) {
                    // append the list of messages
                    for(i = toInsert.length - 1; i >= 0; --i) {
                        var data = toInsert[i];
                        id = data.id;

                        var icon = data.body.icon;
                        var href = data.body.href;

                        var status = data.status;
                        var message = applyTokens(data.body.body, data.params);
                        var timestamp = data.timestamp;

                        var li = msgLiTpl.clone();

                        var now = moment.unix(timestamp);

                        li.attr('id', 'm-'+id).addClass(status);
                        li.find('a').attr('href', href);
                        li.find('span').html(message);
                        li.find('time').html(now.fromNow()).attr('title', now.format('LLLL'));
                        li.find('img').attr('src', icon);
                        content.append(li);
                    }
                    footerText = 'See All';
                }
                else {
                    footerText = 'No Messages'
                }

                // update the actual DOM
                $(settings.list).children('.footer').children('a').html(footerText);
                $(settings.content).replaceWith(content);

                // update the counter (duplicate selector intentional)
                var unreadTotal = $(settings.content).children('li.unread').length;

                // set the counter

                $(self).attr('data-unread', unreadTotal).children(settings.counter).html(unreadTotal ? unreadTotal : '');

            }

            /**
             * @private
             */
            function updateRelativeTimes() {
                $('#messages').children('li.message').each(function(k, v) {
                    var msgId = this.id.split('-')[1];
                    if(typeof messages[msgId] !== 'undefined') {
                        var timestamp = messages[msgId].timestamp;
                        $(this).find('time').text(moment.unix(timestamp).fromNow());
                    }
                });
            }


            /**
             * @private
             * @param string
             * @param tokens
             */
            function applyTokens(string, tokens) {
                $.each(tokens, function(token, value) {
                    if(typeof value == 'object') {
                        value = value[0];
                    }
                    string = string.replace(':'+token, '<b>'+value+'</b>');
                });
                return string;
            }


            /**
             * Socket Event
             * @private
             */
            var onOpen = function() {
                if(settings.debug) { console.log('onOpen() triggered'); }
                send({subscribe : true});
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
                    // put message in messages object, override if message already existed (ensure uniqueness)
                    messages[msgId] = amqpMsg;
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
                    // put message in messages object, override if message already existed (ensure uniqueness)
                    messages[msgId] = amqpMsg;
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
                + (settings.secure ? settings.sslport : settings.port);
            var conn;
            var messages = {};
            var attemptReconnect = true;
            var domTimeout;


            // open/close listener
            $(settings.counter).on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                var ii;

                // if it's *about* to be opened, set the relative times so they don't "flash"
                if($(self).hasClass('open')) {
                    clearInterval(ii);
                }
                else {
                    updateRelativeTimes();
                    ii = setInterval(updateRelativeTimes, 9000);
                }

                $(self).toggleClass('open');

                var fn = function(e) {
                    // if we clicked on the list, do nothing except reattach the listener
                    if($(e.target).closest(settings.list).length == 1) {
                        $('html').one('click', fn);
                    }
                    else {
                        $(self).removeClass('open');
                        clearInterval(ii);
                    }
                };
                $('html').one('click', fn);
            });


            // mark-as-read listener
            $(settings.list).on('click', 'a', function(e) {
                var pip = $(this).hasClass('pip');
                var action = $(this).hasClass('action') && $(this).closest('li').hasClass('unread');

                if(pip || action) {
                    var msgId = $(this).closest('li.msg').attr('id').split('-')[1];
                    send({ack : msgId});

                    if(pip) {
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        return false;
                    }
                }
                return true;
            });


            // testing: toggle indicator
            if(settings.debug) {
                var bell = $j('#infoPanel').children('li.bell');
                var a = $j('#go-msg');
                // test add remove count
                $j('#header a.logo').on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if(bell.attr('data-unread') == 0) {
                        var i = parseInt(Math.random() * 26 + 1);
                        a.html(i);
                        bell.attr('data-unread', i);
                    }
                    else {
                        a.html('');
                        bell.attr('data-unread', '0');
                    }
                });
            }

            // attempt a connection
            establishConnection();
        });
    }
} (jQuery));
