/* Implmentation of Schoolbox Websocket Notifications */

(function($) {

    $.fn.notifier = function(options) {
        var settings = $.extend({
            secure: 'false',
            host: '192.168.1.1',
            port: '8080',
            sslport: '8081',
            prefetch: 5
        }, options);

        // cast boolean from potential string
        settings.secure = (settings.secure === 'true' || settings.secure === true);

        return this.each(function() {

            // detect websocket support
            var support = "MozWebSocket" in window ? 'MozWebSocket' : ("WebSocket" in window ? 'WebSocket' : null);
            if (support == null) {
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
            }

            function pollReconnect() {
                if(attemptReconnect) {
                    setTimeout(establishConnection, 2000);
                }
            }

            /**
             * @private
             * @param data
             */
            function send(data) {
                if(conn) {
                    conn.send(JSON.stringify(data));
                }
            }

            /**
             * @private
             */
            function updateDOM() {

                var dom = $(self);

                // if the avatar doesn't have a listener attached, do initialisation
                if(typeof dom.data("events") === 'undefined' || typeof dom.data("events").click === 'undefined') {

                    dom.attr('title', 'View Messages');

                    var msgs = $('<ul/>').attr('id', 'messages');
                    var ml = $('<li/>').attr('id','message-list').addClass('hide');

                    ml.append(msgs);

                    var ul = dom.closest('ul').append(ml);


                    dom.click(function(e) {
                        e.preventDefault();
                        $('#message-list').toggleClass('hide');
                    });
                }

                // do the following on every DOM update request

                var newlist = $('<ul/>').attr('id', 'messages'),
                    toInsert = [],
                    id;

                for(id in messages) {
                    // insert into temp array
                    toInsert.push(messages[id]);
                }

                toInsert.sort(function(a,b) {
                    // sort ascending by timestamp (we'll iterate descending)
                    return (a.timestamp == b.timestamp ? 0 : (a.timestamp < b.timestamp ? -1 : 1));
                });

                var i;

                var msgLiTpl = $('<li/>').addClass('message').append(
                    $('<a/>').addClass('action').attr('href', '#').append(
                        $('<img/>').attr('width', '32').attr('height', '32')
                    ).append(
                        $('<p/>').addClass('descript')
                    ).append(
                        $('<time/>').addClass('time')
                    )
                );

                newlist.append('<li/>').append($('<h4/>').text('Notifications'));

                for(i = toInsert.length - 1; i >= 0; --i) {
                    id = toInsert[i].id;

                    var status = toInsert[i].status;
                    var body = toInsert[i].body;
                    var message = body.message;
                    var humanTime = body.datetime.date;

                    var li = msgLiTpl.clone();

                    li.attr('id', 'm-'+id).addClass(status);
                    li.find('p').html(message);
                    li.find('time').html(humanTime);
                    newlist.append(li);
                }

                $('#messages').replaceWith(newlist);

            }


            /**
             * Socket Event
             * @private
             */
            var onOpen = function() {
                send({subscribe : true});
                $(self).addClass('socket connected');
            }

            /**
             * Socket Event
             * @private
             * @param e
             */
            var onError = function(e) {
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
                var data;
                try {
                    data = JSON.parse(e.data)

                    // is there a better way to do this? there may be multiple commands within a single message
                    if(data.subscribe)      { self.pipeSubscribe(data.subscribe); }
                    if(data.fetch)          { self.pipeFetch(data.fetch); }
                    if(data.push)           { self.pipePush(data.push); }
                    if(data.mark_as_read)   { self.pipeMarkAsRead(data.mark_as_read); }
                    if(data.error)          { self.pipeError(data); }
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
                $(self).removeClass('connected');
                pollReconnect();
            }


            /**
             * Response Event
             * @privileged
             * @param data
             */
            this.pipeSubscribe = function(data) {
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
                $.each(data, function(msgId, amqpMsg) {
                    // put message in messages object, override if message already existed (ensure uniqueness)
                    messages[msgId] = amqpMsg;
                    updateDOM();
                });
            }

            /**
             * Response Event
             * @privileged
             * @param data
             */
            this.pipePush = function(data) {
                $.each(data, function(msgId, amqpMsg) {
                    // put message in messages object, override if message already existed (ensure uniqueness)
                    if(!messages[msgId] || messages[msgId].status == 'read') {
                        messages[msgId] = amqpMsg;
                    }
                    updateDOM();
                });
            }

            /**
             * Response Event
             * @privileged
             * @param data
             */
            this.pipeMarkAsRead = function(data) {
                $.each(data, function(i, msgId) {
                    // if we haven't seen this message, ignore this notification
                    if(typeof messages[msgId] === 'undefined') {
                        // jQuery loop equiv for "continue"
                        return true;
                    }

                    messages[msgId].status = 'read';
                    updateDOM();

                    return true;

                });
            }

            /**
             * Response Event
             * @privileged
             * @param data
             */
            this.pipeError = function(data) {
                switch(data.code) {
                    case 401:
                        alert('You need to log in');
                        attemptReconnect = false;
                        break;
                }
                if(typeof data.retry !== 'undefined' && data.retry === true) {
                    attemptReconnect = true;
                    conn.close();
                }
                else {
                    conn.close(1011, data.error);
                }
            }


            /* constructor method execution starts here */

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
