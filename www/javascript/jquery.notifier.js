/* Implmentation of Schoolbox Websocket Notifications */

(function($) {

    $.fn.notifier = function(options) {
        var settings = $.extend({
            host: '192.168.1.1',
            port: '8080'
        }, options);

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

                for(i = toInsert.length - 1; i >= 0; --i) {
                    id = toInsert[i].id;
                    var status = toInsert[i].status;
                    var body = toInsert[i].body;
                    var message = body.message;
                    var humanTime = body.datetime.date;
                    var li = $('<li/>').addClass('message').addClass(status).attr('id', 'm-'+id).attr('title', id);
                    li.append($('<p/>').html(message));
                    li.append($('<p/>').html(humanTime));
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
            }

            /**
             * Socket Event
             * @private
             * @param e
             */
            var onError = function(e) {
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
                pollReconnect();
            }


            /**
             * Response Event
             * @privileged
             * @param data
             */
            this.pipeSubscribe = function(data) {
                send({fetch : 15});
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
            var url = 'ws://' + settings.host + ':' + settings.port;
            var conn;
            var messages = {};
            var attemptReconnect = true;

            // attempt a connection
            establishConnection();

        });
    }
} (jQuery));
