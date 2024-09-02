/* Implmentation of Schoolbox Websocket Notifications */

(function ($) {
    $.fn.notifier = function (options) {
        var settings = $.extend(
            {
                // selectors

                counter: "[data-unread-count]",
                content: "#msg-content",
                list: "#message-list",
                readall: "#msg-mar",
                alertBox: "#alert-dialog",
                alertList: "#alert-content",
                footer: "[data-msgfooter-action]",
                modalTitle: "[data-modal-title]",

                // socket configuration

                prefetch: 5,
                maxstack: 3,
                debug: false,
                domwait: 1000,
                transport: "socket",
                timeout: 500,
                maxRetry: 10,
                alertTtl: 30,

                // data attributes

                counterUnread: "unread",
                tplItem: "msgitem",
                tplItemHref: "msgitem-href",
                tplItemThumb: "msgitem-thumb",
                tplItemTitle: "msgitem-title",
                tplItemTime: "msgitem-time",
                tplItemAck: "msgitem-ack",

                // internal configuration

                itemUnread: "unread",
                itemMaxDisplayed: 100,
                itemTimeFormat: "LLLL",
                itemTextEmphasis: "b",

                // have we run the auto notification reader (auto dismiss notif)
                autoReadNotif: false,

                // dictionary values

                dictItemOthers: "others",
                dictItemAnd: "and",
                dictFooterSeeAll: "See All",
                dictFooterNoMessages: "No Notifications",
            },
            options,
        );

        var backoff = 0;

        return this.each(function () {
            // detect websocket support
            if (window.WebSocket === undefined || settings.transport === "poll") {
                console.warn("Websockets are not supported, cannot get notifications");
            }

            /**
             * Establish a connection via websocket
             * @private
             */
            function establishConnection() {
                conn = new window.WebSocket(url);
                conn.onopen = onOpen;
                conn.onerror = onError;
                conn.onmessage = onMessage;
                conn.onclose = onClose;

                if (settings.debug) {
                    console.log("Created new socket connection");
                }
            }

            function pollReconnect() {
                var jitter;

                if (attemptReconnect && backoff < settings.maxRetry) {
                    jitter = parseInt(
                        Math.random() * settings.timeout * Math.min(settings.maxRetry, Math.pow(2, backoff++)),
                    );

                    if (settings.debug) {
                        console.log("Polling for connection, attempt #" + backoff + ", waiting " + jitter + "ms");
                    }

                    setTimeout(establishConnection, jitter);
                } else {
                    if (settings.debug) {
                        console.log("Poll timeout exceeded. Giving up.");
                    }
                    $(settings.list).children(".footer").children("a").html("Could not connect");
                }
            }

            /**
             * @private
             * @param data
             */
            function send(data) {
                if (conn) {
                    if (settings.debug) {
                        console.log("Sending data to server");
                    }
                    conn.send(JSON.stringify(data));
                } else {
                    if (settings.debug) {
                        console.log("No connection: not sending data");
                    }
                }
            }

            /**
             * @private
             */
            function updateDOM() {
                if (settings.debug) {
                    console.log("Updating DOM");
                }

                // do the following on every DOM update request

                // replicate the list and delete its contents
                var content = $(settings.content).clone(false).empty(), // $('<ul/>').attr('id', 'messages'),
                    toInsert = [],
                    id;

                for (id in messages) {
                    // insert into temp array
                    messages[id].id = id;
                    toInsert.push(messages[id]);
                }

                // sort descending by timestamp
                toInsert.sort(function (a, b) {
                    return a.timestamp == b.timestamp ? 0 : a.timestamp < b.timestamp ? 1 : -1;
                });

                var footerText = "";

                // Show a max of settings.itemMaxDisplayed messages
                toInsert.slice(0, settings.itemMaxDisplayed).forEach(function (item) {
                    // append the list of messages
                    var li = settings.tpl.clone();

                    applyMessageData(li, item);

                    content.append(li);
                });

                if (toInsert.length > 0) {
                    footerText = settings.dictFooterSeeAll;
                } else {
                    footerText = settings.dictFooterNoMessages;
                }

                // update the actual DOM
                $(settings.list).find(settings.footer).html(footerText);
                $(settings.content).replaceWith(content) /*.foundation('equalizer')*/;

                // set the counter
                var unreadTotal = toInsert.filter(function (a) {
                    return a.status === settings.itemUnread;
                }).length;
                $("[data-" + settings.counterUnread + "]").attr("data-" + settings.counterUnread, unreadTotal);
                var unreadTotalLabel = unreadTotal;
                if (unreadTotal === 0) {
                    unreadTotalLabel = "";
                }
                $(settings.counter).html(unreadTotalLabel);

                clearInterval(ii);
                ii = setInterval(updateRelativeTimes, 9000);

                // if we have badge support, then set the unread count of notifications
                if ("setAppBadge" in navigator) {
                    // Display the number of unread messages.
                    if (unreadTotal > 0) {
                        navigator.setAppBadge(unreadTotal);
                    } else {
                        navigator.clearAppBadge();
                    }
                }

                // if we haven't run this yet, then auto read the messages
                if (!settings.autoReadNotif) {
                    self.autoUnread();
                }
            }

            /**
             * @private
             */
            function updateRelativeTimes() {
                // don't render a future time if server clock happens to be ahead of client
                var now = Math.floor(new Date().getTime() / 1000);

                $(settings.list)
                    .children("[data-" + settings.tplItem + "]")
                    .each(function (k, v) {
                        var msgId = this.id.split("-")[1];
                        if (typeof messages[msgId] !== "undefined") {
                            var timestamp = messages[msgId].timestamp;
                            $(this)
                                .find("[data-" + settings.tplItemTime + "]")
                                .text(dayjs.unix(Math.min(timestamp, now)).fromNow());
                        }
                    });
            }

            /**
             * @private
             * @param {string} string
             * @param {object} tokens
             */
            function applyTokens(string, tokens) {
                var b = "<" + settings.itemTextEmphasis + ">",
                    _b = "</" + settings.itemTextEmphasis + ">";

                // don't blow up if tokens is undefined
                $.each(tokens || {}, function (token, value) {
                    // arrange instance into an array so they can be sorted
                    var instances = [];

                    // insert instances into temp array to allow sorting by time
                    var hash;
                    for (hash in value) {
                        var v = value[hash];
                        instances.push(v);
                    }

                    instances.sort(function (a, b) {
                        // sort descending by timestamp (which will give highest precedence to most recent)
                        return a.time == b.time ? 0 : a.time < b.time ? 1 : -1;
                    });

                    var i,
                        stack = [],
                        plain = false;
                    for (i = 0; i < instances.length; ++i) {
                        if (instances[i].mode == 2) {
                            plain = true;
                        }
                        if (instances[i].mode < 0) {
                            if (i == 0) {
                                stack = [instances[i].text];
                            }
                        } else if (instances[i].mode > 0) {
                            stack.push(instances[i].text);
                        } else {
                            stack = [instances[i].text];
                        }
                    }

                    if (stack.length > settings.maxstack) {
                        // trim
                        var excess = stack.length - settings.maxstack + 1;
                        stack = [stack[0], stack[1], excess + " " + settings.dictItemOthers];
                    }

                    // All stack items must be HTML-escaped
                    stack = stack.map(escapeHtmlChars);
                    if (!plain) {
                        stack = stack.map(function (val) {
                            return b + val + _b;
                        });
                    }
                    var joinedStack =
                        stack.length === 1
                            ? stack[0]
                            : stack.slice(0, -1).join(", ") + " " + settings.dictItemAnd + " " + stack.slice(-1);
                    string = string.replace(":" + token, joinedStack);
                });
                return string;
            }

            /**
             * Entity map for escapeHtmlChars
             */
            var entityMap = {
                "&": "&amp;",
                "<": "&lt;",
                ">": "&gt;",
                '"': "&quot;",
                "'": "&#39;",
                "/": "&#x2F;",
                "`": "&#x60;",
                "=": "&#x3D;",
            };

            /**
             * HTML-escapes text.
             *
             * @private
             * @param {string} text
             * @returns {string} escaped text
             */
            function escapeHtmlChars(text) {
                return String(text).replace(/[&<>"'`=\/]/g, function fromEntityMap(s) {
                    return entityMap[s];
                });
            }

            /**
             * @private
             * @param {jQuery} element
             * @param {object} data;   defined as [id, href, timestamp, status, icon, body(, params)]
             */
            function applyMessageData(element, data) {
                var now = dayjs.unix(data.timestamp),
                    msg = applyTokens(data.body, data.params);

                element
                    .attr("id", "m-" + data.id)
                    .attr("data-status", data.status)
                    .addClass("status-" + data.status);
                var hashPos = data.href.lastIndexOf("#");
                if (hashPos > 1 && data.href.substr(0, hashPos) === location.pathname) {
                    element.find("[data-" + settings.tplItemHref + "]").on("click", function () {
                        location.href = this.href;
                        location.reload();
                    });
                }

                element.find("[data-" + settings.tplItemHref + "]").attr("href", data.href);
                element.find("[data-" + settings.tplItemTitle + "]").html(msg);
                element
                    .find("[data-" + settings.tplItemTime + "]")
                    .html(now.fromNow())
                    .attr("title", now.format(settings.itemTimeFormat));
                element.find("[data-" + settings.tplItemThumb + "]").attr("src", data.icon);
            }

            /**
             * Socket Event
             * @private
             */
            var onOpen = function () {
                if (settings.debug) {
                    console.log("onOpen() triggered");
                }

                // reset backoff counter
                backoff = 0;

                // initiate handshake
                send({ subscribe: true });

                // update UI to reflect connection success
                $(self).addClass("socket connected").data("connected", true);
                $(settings.counter).attr("title", "View Messages");
            };

            /**
             * Socket Event
             * @private
             * @param e
             */
            var onError = function (e) {
                if (settings.debug) {
                    console.log("onError() triggered");
                }
                $(self).removeClass("connected").data("connected", false);
                var data;
                try {
                    data = JSON.parse(e.data);
                } catch (e) {
                    data = e.data;
                }

                // TODO use this somehow
            };

            this.autoUnread = function (data) {
                if (settings.debug) {
                    console.log("auto unread called.");
                }
                /* On page load, if the url path matches the path of the unread notifications, we should mark that as read
                 *
                 * originally this matched the exact notif href, but some of those have specific tabs, or references  / anchors,
                 * that are not navigated to when using the interface. so we need to match on path instead.
                 */
                const href = document.location.pathname;
                document.querySelectorAll("[data-msgitem-href]").forEach((element) => {
                    if (!settings.autoReadNotif) {
                        // no page found yet, keep looping
                        let elmHref = new URL(element.getAttribute("href"), window.location.href);
                        if (elmHref.pathname === href) {
                            // get parent where we could mark as read
                            let message = element.closest("[data-msgitem]");
                            // if this is unread, mark as read, and exit
                            if ("unread" === message.dataset.status) {
                                if (settings.debug) {
                                    console.log("found message to mark as read.");
                                }
                                var msgId = message.getAttribute("id").split("-")[1];
                                send({ ack: msgId });
                                settings.autoReadNotif = true;
                            }
                        }
                    }
                });
            };

            /**
             * Socket Event
             * @private
             * @param e
             */
            var onMessage = function (e) {
                if (settings.debug) {
                    console.log("onMessage() triggered");
                }
                var data;
                try {
                    data = JSON.parse(e.data);

                    // is there a better way to do this? there may be multiple commands within a single message
                    if (data.subscribe) {
                        self.pipeSubscribe(data.subscribe);
                    }
                    if (data.fetch) {
                        self.pipeFetch(data.fetch);
                    }
                    if (data.push) {
                        self.pipePush(data.push);
                    }
                    if (data.ack) {
                        self.pipeMarkAsRead(data.ack);
                    }
                    if (data.alert) {
                        self.pipeAlert(data.alert);
                    }
                    if (data.error) {
                        self.pipeError(data);
                    }
                } catch (err) {
                    data = e.data;
                }

                // TODO use this somehow
            };

            /**
             * Socket Event
             * @private
             * @param e
             */
            var onClose = function (e) {
                if (settings.debug) {
                    console.log("onClose() triggered");
                }
                $(self).removeClass("connected").data("connected", false);
                pollReconnect();
            };

            /**
             * Stores notification instances in a stacked fashion
             *
             * @private
             * @param msgId
             * @param amqpMsg
             * @param mode
             */
            var storeMessage = function (msgId, amqpMsg, mode) {
                // if no message set for this ID, just set it
                if (typeof messages[msgId] == "undefined") {
                    // not previously set, just insert
                    messages[msgId] = amqpMsg;
                    return;
                }

                // check the titles match; discard the new one if they don't
                if (messages[msgId].body != amqpMsg.body) {
                    if (settings.debug) {
                        console.log("Messages with matching IDs have conflicting titles");
                    }
                    return;
                }

                // get the existing params and status
                var params = messages[msgId].params;
                var status = messages[msgId].status;

                // override the base message data if newer
                if (amqpMsg.timestamp >= messages[msgId].timestamp) {
                    messages[msgId] = amqpMsg;
                }

                // deal with param stacking
                $.each(amqpMsg.params, function (token, paramSet) {
                    $.each(paramSet, function (hash, param) {
                        switch (param.mode) {
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
                messages[msgId].status = mode == "push" ? amqpMsg.status : status;
            };

            /**
             * Response Event
             * @privileged
             * @param data
             */
            this.pipeSubscribe = function (data) {
                if (settings.debug) {
                    console.log("Message from server: parsing subscribe");
                }
                // don't try a fetch if the subscription came back false
                if (data === true) {
                    $(self).data("subscribed", true);
                    send({ fetch: settings.prefetch });
                }
            };

            /**
             * Response Event
             * @privileged
             * @param data
             */
            this.pipeFetch = function (data) {
                if (settings.debug) {
                    console.log("Message from server: parsing fetch");
                }
                $.each(data, function (msgId, amqpMsg) {
                    storeMessage(msgId, amqpMsg, "fetch");
                });
                $(self).data("message-count", messages.length);
                clearTimeout(domTimeout);
                domTimeout = setTimeout(updateDOM, settings.domwait);
            };

            /**
             * Response Event
             * @privileged
             * @param data
             */
            this.pipePush = function (data) {
                if (settings.debug) {
                    console.log("Message from server: parsing push");
                }
                $.each(data, function (msgId, amqpMsg) {
                    storeMessage(msgId, amqpMsg, "push");
                });
                $(self).data("message-count", messages.length);
                clearTimeout(domTimeout);
                domTimeout = setTimeout(updateDOM, settings.domwait);
            };

            /**
             * Response Event
             * @privileged
             * @param data
             */
            this.pipeMarkAsRead = function (data) {
                if (settings.debug) {
                    console.log("Message from server: parsing mark_as_read");
                }
                $.each(data, function (i, msgId) {
                    // if we haven't seen this message, ignore this notification
                    if (typeof messages[msgId] === "undefined") {
                        // jQuery loop equiv for "continue"
                        return true;
                    }

                    messages[msgId].status = "read";

                    return true;
                });
                updateDOM();
            };

            this.pipeAlert = function (data) {
                if (settings.debug) {
                    console.log("Message from server: parsing alert");
                }
                // while there will always only be one, it follows the same data structure as the others
                $.each(data, function (msgId, amqpMsg) {
                    var ii,
                        box = $(settings.alertBox),
                        title = box.find(settings.modalTitle),
                        list = $(settings.alertList),
                        item = settings.alertTpl.clone(false),
                        noModal = typeof box.data("ui-dialog") == "undefined",
                        closeDialog = function () {
                            box.foundation("reveal", "close");
                            clearTimeout(ii);
                        };

                    // jam that data back in there!
                    amqpMsg.id = msgId;

                    applyMessageData(item, amqpMsg);

                    // Do not re-append the same message
                    if (list.find("#" + item.attr("id")).length === 0) {
                        list.append(item);
                    }

                    if (!title.html().length) {
                        title.text(amqpMsg.meta.title);
                    }

                    $(box).foundation("reveal", "open");

                    if (settings.alertOpen) {
                        // reset the countdown
                        clearTimeout(ii);
                    }

                    ii = setTimeout(closeDialog, (amqpMsg.meta.ttl || settings.alertTtl) * 1000);

                    // jQuery loop equiv for "break"
                    return false;
                });
            };

            /**
             * Response Event
             * @privileged
             * @param data
             */
            this.pipeError = function (data) {
                if (settings.debug) {
                    console.log("Message from server: parsing error");
                }
                $(self).data("connected", false);
                switch (data.code) {
                    case 401:
                        console.warn("Either no session, or does not match parent.");
                        attemptReconnect = false;
                        break;
                    case 503:
                        console.warn("Service is unavailable: " + !!data.retry ? "cannot retry" : "can retry");
                        attemptReconnect = !!data.retry;
                        break;
                }
                conn.close();
            };

            /* constructor method execution starts here */
            if (settings.debug) {
                console.log("Constructing Notifier on", this);
            }

            var self = this;
            let url = "wss://" + window.location.host + "/websocket";
            var conn;
            var messages = {};
            var attemptReconnect = true;
            var domTimeout;
            var ii;

            // yank the template content out of their containers
            settings.tpl = $(settings.content)
                .find("[data-" + settings.tplItem + "]")
                .clone(false);
            settings.alertTpl = $(settings.alertList)
                .find("[data-" + settings.tplItem + "]")
                .clone(false);
            $(settings.content).empty();
            $(settings.alertList).empty();

            // whenever the flyout is about to open, update relative times so they appear correctly
            $(settings.counter).on("click", function (e) {
                e.preventDefault();
                updateRelativeTimes();
            });

            // mark-as-read listener
            $(settings.list).on("click", "a", function (e) {
                var $a = $(this);
                var $item = $a.closest("[data-" + settings.tplItem + "]");
                var pip = typeof $a.data(settings.tplItemAck) != "undefined";
                var action =
                    typeof $a.data(settings.tplItemHref) != "undefined" && $item.data("status") == settings.itemUnread;

                if (pip || action) {
                    var msgId = $item.attr("id").split("-")[1];
                    send({ ack: msgId });

                    if (pip) {
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                    }
                }
            });

            // mark-ALL-as-read listener
            $(settings.readall).on("click", function (e) {
                e.preventDefault();
                e.stopPropagation();

                // Mark all messages unread, regardless of whether or not they are shown in DOM
                var unreads = [];
                for (var id in messages) {
                    var message = messages[id];
                    if (message.status === settings.itemUnread) {
                        unreads.push(id);
                    }
                }

                send({ ack: unreads });
            });

            // listen for alert open/close; we bind globally, but ignore anything not relevant to us
            $(document).on("open.fndtn.reveal close.fndtn.reveal", "[data-reveal]", function (e) {
                if ($(e.target).is($(settings.alertBox))) {
                    settings.alertOpen = e.type == "open";
                }
            });

            // once the alert box has finished closing, clear it out for next use
            $(document).on("closed.fndtn.reveal", "[data-reveal]", function (e) {
                var target = $(e.target),
                    box = $(settings.alertBox);

                // remove the content of the alert box
                if (target.is(box)) {
                    box.find(settings.modalTitle).empty();
                    $(settings.alertList).empty();
                }
            });

            // attempt a connection
            establishConnection();
        });
    };

    // declare string functions (courtesy of http://stackoverflow.com/a/2729694/1877343)
    if (!String.prototype.reverse) {
        String.prototype.reverse = function () {
            return this.split("").reverse().join("");
        };
    }

    if (!String.prototype.replaceLast) {
        String.prototype.replaceLast = function (what, replacement) {
            return this.reverse().replace(new RegExp(what.reverse()), replacement.reverse()).reverse();
        };
    }
})(jQuery);
