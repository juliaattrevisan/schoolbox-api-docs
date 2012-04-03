(function($) {

$.widget("ui.chat",  {
    options: {
        state: 'Active',
        category_id: null,
        component_id: null,
        user_id: null,
        user_name: null,
        user_perm: 1, // 1 Read Access, 2 Write Access, 3 Full Access
        log_el: 'ul.chat-messages',
        users_el: 'ul.chat-users',
        body_el: 'textarea',
        send_el: 'input[type="submit"]',
        base_freq: 3,
        poll_freq: 6, // seconds,
        recv_freq: 3
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
			} else {
                $body_el.animate({ backgroundColor: "#f0f0f0" }, 100).animate({ backgroundColor:'#fff'}, 100);
                $body_el.parent().animate({ backgroundColor: "#f0f0f0" }, 100).animate({ backgroundColor:'#fff'}, 100);
			}
        });

        this.options.body_el.keypress(function (event) {
            /* Execute send on enter key pressed */
            if (event.keyCode == 13) {
                var $body_el = $self.options.body_el;

				event.preventDefault();
                
				if ($body_el.val().length > 0) {
                    $self._send($self);
				} else {
                    $body_el.animate({backgroundColor: "#f0f0f0" }, 100).animate({backgroundColor:'#fff'}, 100);
                    $body_el.parent().animate({backgroundColor: "#f0f0f0" }, 100).animate({backgroundColor:'#fff'}, 100);
				}
            }
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

        if ($hours > 13 ) {
            $hours -= 12;
            $meridian = 'pm'
        }

        var $hours = ($hours > 9 ? '' : '0') + $hours;

        var $time = $hours + ':' + $minutes + ':' + $seconds + ' ' + $meridian;

        var $message = {
            method: 'send',
            category_id: $self.options.category_id,
            component_id: $self.options.component_id,
            user_id: $self.options.user_id,
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
        var $message_el = $('<li></li>').addClass('chat-message-' + $message.type);

        /* If admin */
        if ($message.user_perm > 1) {
            $message_el.addClass('chat-message-user-admin');
        }

        if ($message.user_id == this.options.user_id) {
            $message_el.addClass('chat-message-user-me');
        }

        $message_el.html('<span class="chat-user"><span class="triangulate"></span><span class="chat-username">'+$message.name+'</span><span class="chat-time">'+$message.time+'</span></span><span class="chat-message">'+$message.body+'</span>');

        this.options.log_el.append($message_el).prop({ scrollTop: this.options.log_el.prop("scrollHeight") });

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

                    var $user_el = $('<li></li>');

                    $user_el.text($user.name).data('user_id', $user.user_id);

                    //every two add in the dark class chimchim
                    if ($x%2 != 0) {
                        $user_el.addClass('dark');
                    }
                    $x++;

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
                        var $mute_el = $('<a href="#">mute</a>');

                        if ($data.config.muted_users != undefined && $.inArray($user.user_id, $data.config.muted_users) > -1) {
                            $user_el.addClass('chat-user-muted');
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
                    $self.options.body_el.attr('disabled', 'disabled');
                } else {
                    $self.options.body_el.removeAttr('disabled');
                }

                /* Change state */
                if ($data.config.state == 'Active') {
                    $('.light', $self.element).css('background-position', "bottom");
                    $('.light', $self.element).addClass('is-active');
                } else {
                    $j('.light', $self.element).css('background-position', "top");

                    $self.options.body_el.attr('disabled', 'disabled');
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

/*
render_config: function (config) {
    if (config.state == 'Active') {
        $j('.light').css('background-position', "bottom");
        $j('.light').addClass('is-active');
    } else {
        this.render_users({});

        $j('.light').css('background-position', "top");

        if(!this.is_admin()) {
            $j('textarea.chat-message-box').attr('disabled', 'true');
        }
    }
}
*/