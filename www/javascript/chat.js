/* String.format function: http://stackoverflow.com/a/2648463/1877343 */
String.prototype.format = function() {
    var s = this,
        i = arguments.length;

    while (i--) {
        s = s.replace(new RegExp('\\{' + i + '\\}', 'gm'), arguments[i]);
    }
    return s;
};

(function($) {

$.widget("ui.chat",  {
    options: {
        state: 'Active',
        category_id: null,
        component_id: null,
        user_id: null,
        user_ext_id: null,
        user_name: null,
        user_perm: 1, // 1 Read Access, 2 Write Access, 3 Full Access
        log_el: 'ul.chat-messages',
        users_el: 'ul.chat-users',
        body_el: 'textarea',
        send_el: 'input[type="submit"]',
        base_freq: 3,
        poll_freq: 6, // seconds,
        recv_freq: 3,
        max_height: '300px'
    },

    _create: function () {
        var $self = this;

        /* Apply JQuery to elements */
        this.options.body_el = $(this.options.body_el, $self.element);
        this.options.users_el = $(this.options.users_el, $self.element);
        this.options.send_el = $(this.options.send_el, $self.element);
        this.options.log_el = $(this.options.log_el, $self.element);

        this.last_poll = null;

        /* Receive interval */
        this._receive();

        if (this.options.state == 'Active') {

        } else {
            return false;
        }

        this._poll();

        this.interval_cnt = 0;
        this.interval_ref = setInterval(function () {
            var run_time = $self.options.base_freq * 1000 * $self.interval_cnt;

            var recv_time = $self.options.recv_freq * 1000;
            var poll_time = $self.options.poll_freq * 1000 ;

            if (run_time % recv_time == 0 && $self.options.state == 'Active') {
                $self._receive();
            }

            if (run_time % poll_time == 0 && $self.options.state == 'Active') {
                $self._poll();
            }

            $self.interval_cnt++;
        }, this.options.base_freq * 1000);

        /* Attach events */
        this.options.send_el.click(function (event) {
            var $body_el = $self.options.body_el;

            event.preventDefault();

            if ($body_el.val().length > 0) {
                $self._send($self);
			}
        });

        this.options.body_el.keypress(function (event) {
            /* Execute send on enter key pressed */
            if (event.keyCode == 13) {
                var $body_el = $self.options.body_el;

				event.preventDefault();

				if ($body_el.val().length > 0) {
                    $self._send($self);
				}
            }
        });

        // apply some really specific styling
        this.options.log_el.css({
            'max-height' : this.options.max_height,
            'overflow-y' : 'scroll'
        });

    },

    _interval: function () {
        var $self = this;

        var run_time = $self.options.base_freq * 1000 * $self.interval_cnt;

        var recv_time = $self.options.recv_freq * 1000;
        var poll_time = $self.options.poll_freq * 1000 ;

        if (run_time % recv_time == 0) {
            $self._receive();
        }

        if (run_time % poll_time == 0) {
            $self._poll();
        }

        $self.interval_cnt++;
    },

    _send: function ($self) {
        var $d = new Date();

        var $hours = $d.getHours();

        var $minutes = ($d.getMinutes() > 9 ? '' : '0') + $d.getMinutes();
        var $seconds = ($d.getSeconds() > 9 ? '' : '0') + $d.getSeconds();

        var $meridian = 'am';

		/* Allow for noon times to be in PM
		 */
        if ($hours >= 12 ) {

			/* Convert to 12-hour format
			 */
			if ($hours > 13) {
            	$hours -= 12;
			}

            $meridian = 'pm'
        }

        var $hours = ($hours > 9 ? '' : '0') + $hours;

        var $time = $hours + ':' + $minutes + ':' + $seconds + ' ' + $meridian;

        var $message = {
            method: 'send',
            category_id: $self.options.category_id,
            component_id: $self.options.component_id,
            user_id: $self.options.user_id,
            user_ext_id: $self.options.user_ext_id,
            user_perm: $self.options.user_perm,
            last_poll: $self.last_poll,
            name: $self.options.user_name,
            time: $time,
            type: 'user',
            body: $self.options.body_el.val()
        };

        $($self).trigger('send', null, $message);

        $self._append_message($message);

        $.ajax({
            type: 'POST',
            url: '/ajax/chat.php',
            data: $message,
            success: function () {
                $self.options.body_el.val('');
            },
            dataType: 'json'
        });
    },

    _receive: function () {
        var $self = this;

        $.ajax({
            type: 'POST',
            url: '/ajax/chat.php',
            data: {
                method: 'receive',
                category_id: this.options.category_id,
                component_id: $self.options.component_id,
                user_id: this.options.user_id,
                last_poll: this.last_poll
            },
            success: function ($data) {

                /* Output received messages */
                var $log = $data.log;

                for (var $i in $log) {
                    $self._append_message($log[$i]);
                }

                /* Set last poll to now */
                $self.last_poll = $data.time;

            },
            dataType: 'json'
        });

    },

    _append_message: function ($message) {

        var last = this.options.log_el.find('li').last();

        // Am I scrolled to the bottom already? If so scroll down
        // If not, don't - I'm probably browsing through old posts
        var scrollTop = this.options.log_el.prop('scrollTop');
        var height    = this.options.log_el.prop('scrollHeight') - this.options.log_el.prop('offsetHeight');
        var scroll    = (Math.abs(scrollTop - height) === 0);

        if ($message.user_id == last.data('user')) {
            last.find('.card').append('<p class="spaced">{0}</p>'.format($message.body));
        } else {

            var $message_el = $('<li data-user="{0}" class="row"></li>'.format($message.user_id)).addClass('chat-message-' + $message.type);

            /* If admin */
            if ($message.user_perm > 1) {
                $message_el.addClass('chat-message-user-admin');
            }

            var $tpl =
                '<div class="avatar card">' +
                '<img src="/portrait.php?userId={1}&size=square64" title="{0}" />' +
                '<h3>{0} <span class="meta">&bull; {2}</span></h3>' +
                '<p class="spaced">{3}</p>' +
                    //'<p class="chat-username">{2} {0}</p>' +
                '</div>'
                //'<div class="chat-message">' +
                //+ '</div>'
            ;

            $message_el.html($tpl.format($message.name, $message.user_ext_id, $message.time, $message.body));
            this.options.log_el.append($message_el);
        }
        if (scroll) {
            this.options.log_el.prop({ scrollTop: this.options.log_el.prop("scrollHeight") });
        }
    },

    _poll: function () {
        var $self = this;

        $.ajax({
            type: 'POST',
            url: '/ajax/chat.php',
            data: {
                method: 'poll',
                category_id: this.options.category_id,
                component_id: $self.options.component_id,
                user_id: this.options.user_id
            },
            success: function ($data) {

                /* Output received users */
                var $user_list_el = $self.options.users_el;

                var $x = 0;

                $user_list_el.empty();

                for (var $i in $data.users) {
                    var $user = $data.users[$i];

                    var $user_el = $('<li class="actions-small-1"></li>');
					var $tpl =
                        '<div class="list-item">' +
                            '<div class="small-12 avatar card">' +
                                '<img src="/portrait.php?userId={1}&size=square64" title="{0}" />' +
                                '<h3>{0}</h3>' +
                            '</div>' +
                        '</div>';

					$user_el.html($tpl.format($user.name, $user.user_ext_id));
                    $user_el.data('user_id', $user.user_id);

                    /* If admin */
                    if ($user.perm > 1) {
                        $user_el.addClass('chat-user-admin');
                    }

                    /* If self */
                    if ($user.user_id == $self.options.user_id) {
                        $user_el.addClass('chat-user-me');
                    }

                    /* Mute/unmute buttons */
                    if ($self.options.user_perm > $user.perm && $user.user_id != $self.options.user_id) {
                        var $mute_el = $('<nav><a href="#" class="icon-mute" title="Mute" data-interaction-mute></a></nav>');

                        if ($data.config.muted_users != undefined && $.inArray($user.user_id, $data.config.muted_users) > -1) {
                            $user_el.addClass('chat-user-muted');
                            $mute_el.find('a[data-interaction-mute]').removeClass('icon-mute').addClass('icon-approve').attr('title', 'Unmute');
                        }

                        $mute_el.click(function ($event) {
                            $event.preventDefault();

                            var $user_id = $(this).parent().data('user_id');

                            $self._mute_user($user_id);
                        });

                        $mute_el.appendTo($user_el);
                    }

                    $user_el.appendTo($user_list_el);
                }

                /* Act on new config */
                if ($data.config.muted_users != undefined && $.inArray($self.options.user_id, $data.config.muted_users) > -1) {
                    $self.options.body_el.prop('disabled', true);
                } else {
                    $self.options.body_el.prop('disabled', false);
                }

                /* Change state */
                if ($data.config.state == 'Active') {
                    $('.light', $self.element).css('background-position', "bottom");
                    $('.light', $self.element).addClass('is-active');
                } else {
                    $('.light', $self.element).css('background-position', "top");

                    $self.options.body_el.prop('disabled', true);
                }

                var componentID = 'component' + $self.options.component_id;
                if(document.activeElement.id == componentID) {
                    $('#' + componentID + '-chatmessage').focus();
                }

            },
            dataType: 'json'
        });

    },

    _mute_user: function ($user_id) {
        var $self = this;

        $.ajax({
            type: 'POST',
            url: '/ajax/chat.php',
            data: {
                method: 'mute',
                category_id: $self.options.category_id,
                component_id: $self.options.component_id,
                user_id: $user_id
            },
            success: function ($data) {
                $self._poll();
            },
            dataType: 'json'
        });
    },

    get_state: function() {
        if(this.options.users_el.children().length <= 1)
            return 'Inactive';
        return this.options.state;
    }
});

})(jQuery);
