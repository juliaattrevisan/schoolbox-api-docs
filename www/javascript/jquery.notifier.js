/* Implmentation of Schoolbox Websocket Notifications */

(function($) {

    $.fn.notifier = function(options) {
        var settings = $.extend({
            secure: 'false',
            host: '192.168.1.1',
            port: '8080',
            sslport: '8081',
            prefetch: 5,
            debug: false
        }, options);

        // cast boolean from potential string
        settings.secure = (settings.secure === 'true' || settings.secure === true);

        return this.each(function() {

            // detect websocket support
            var support = "MozWebSocket" in window ? 'MozWebSocket' : ("WebSocket" in window ? 'WebSocket' : null);
            if (support == null) {
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

                // if the avatar doesn't have a listener attached, do initialisation
                if(typeof dom.data("events") === 'undefined' || typeof dom.data("events").click === 'undefined') {

                    if(settings.debug) { console.log('Attaching click listener (btw, update your jQuery!)'); }

                    dom.attr('title', 'View Messages');

                    var msgs = $('<ul/>').attr('id', 'messages');
                    var ml = $('<li/>').attr('id','message-list').addClass('hide');

                    ml.append(msgs);

                    var ul = dom.closest('ul').append(ml);

                    // setup mark-as-read
                    ml.delegate('a.pip', 'click', function(ev) {
                        ev.stopPropagation();
                        ev.stopImmediatePropagation();
                        var msgId = $(this).closest('li.message').attr('id').split('-')[1];
                        send({ack : msgId});
                        return false;
                    });


                    dom.click(function(ev) {
                        ev.preventDefault();
                        ev.stopImmediatePropagation();
                        $('#message-list').removeClass('hide');

                        updateRelativeTimes();
                        var ii = setInterval(updateRelativeTimes, 9000);

                        if(settings.debug) { console.log('Open list'); }

                        $('body').one('click', function(e) {
                            $('#message-list').addClass('hide');
                            clearInterval(ii);
                            if(settings.debug) { console.log('Close list', this); }
                        });
                    });
                }

                // do the following on every DOM update request

                var newlist = $('<ul/>').attr('id', 'messages'),
                    toInsert = [],
                    toId = [],
                    id;

                for(id in messages) {
                    // insert into temp array
                    toInsert.push(messages[id]);
                    toId.push(id);
                }

                toInsert.sort(function(a,b) {
                    // sort ascending by timestamp (we'll iterate descending)
                    return (a.timestamp == b.timestamp ? 0 : (a.timestamp < b.timestamp ? -1 : 1));
                });

                var i;

                // template for message list items
                var msgLiTpl = $('<li/>').addClass('message').append(
                    $('<a/>').addClass('action').attr('href', '#').append(
                        $('<a/>').addClass('pip').attr('title', 'Mark as read')
                    ).append(
                        $('<img/>').attr('width', '32').attr('height', '32')
                    ).append(
                        $('<p/>').addClass('descript')
                    ).append(
                        $('<time/>').addClass('time')
                    )
                );

                newlist.append('<li/>').append($('<h4/>').text('Messages'));

                if(toInsert.length > 0) {
                    // append the list of messages
                    for(i = toInsert.length - 1; i >= 0; --i) {
                        id = toId[i];
                        var body = toInsert[i];

                        var status = body.status;
                        var message = body.text;
                        var timestamp = body.timestamp;
                        var icon = body.icon;
                        var href = body.href;

                        var li = msgLiTpl.clone();

                        var now = moment.unix(timestamp);

                        li.attr('id', 'm-'+id).addClass(status);
                        li.find('a').attr('href', href);
                        li.find('p').html(message);
                        li.find('time').html(now.fromNow()).attr('title', now.format('LLLL'));
                        li.find('img').attr('src', icon);
                        newlist.append(li);
                    }

                    // append a "see all" item
                    newlist.append(
                        $('<li/>').append(
                            $('<a/>').attr('href', '#').html('See all')
                        )
                    );
                }
                else {
                    newlist.append($('<li/>').html('No messages'));
                }

                $('#messages').replaceWith(newlist);

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
             * Socket Event
             * @private
             */
            var onOpen = function() {
                if(settings.debug) { console.log('onOpen() triggered'); }
                send({subscribe : true});
                $(self).addClass('socket connected');
            }

            /**
             * Socket Event
             * @private
             * @param e
             */
            var onError = function(e) {
                if(settings.debug) { console.log('onError() triggered'); }
                $(self).removeClass('connected');
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
                $(self).removeClass('connected');
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
                updateDOM();
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
                    if(!messages[msgId] || messages[msgId].status == 'read') {
                        messages[msgId] = amqpMsg;
                    }
                });
                updateDOM();
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

            // attempt a connection
            establishConnection();

        });
    }
} (jQuery));
