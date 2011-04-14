function Chat (config) {
    this.send_data = {
        config: {},
        messages: {}
    };

    this.config = config;
    this.update();
    this.interval();
}

Chat.prototype = {
    interval: function () {
        var self = this;

        this.interval_ref = setInterval(function () {
            self.update();
        }, 4000);
    },

    update: function () {
        var self = this;

        this.send_data.config = this.config;

        $j.ajax({
            type: 'POST',
            url: '/ajax/chat.php',
            data: {send: this.send_data},
            success: function (data) {
                self.recv(data);
            },
            dataType: 'json'
        });

        this.send_data.messages = {};
    },

    recv: function (data) {
        this.config = data.config;
        this.users = data.users;

        this.render_config(data.config);

        if (this.config.state == 'Active' || this.is_admin()) {
            this.render_users(data.users);
            this.render_messages(data.messages);
        } else {
            this.render_inactive(data.messages);
        }
    },

    send: function () {
        var self = this;
        var message = $j('#component'+this.config.component_id+' textarea[name="message"]');

        var data = {
            messages: {
                0: {
                    title: self.config.name.title,
                    firstname: self.config.name.firstname,
                    lastname: self.config.name.lastname,
                    user_id: self.config.user_id,
                    type: 'user',
                    time: (new Date()).toString(),
                    body: message.val()
                }
            }
        };
	
        message.val('');
	
        this.render_messages(data.messages);
        $j.extend(this.send_data, data);
    },

    mute: function (user_id) {
        var self = this;
        
        if (self.config.muted_users[user_id] != 'true') {
            self.config.muted_users[user_id] = 'true';
        } else {
            delete self.config.muted_users[user_id];
        }

        this.render_users(self.users);
    },

    clear: function () {
        this.config.clear = true;
    },

    render_messages: function (messages) {
        var self = this;

        var messages_el = $j('#component'+this.config.component_id+' ul.chat-messages');
		
        for (var i in messages) {
            var message = messages[i];

            message.time = (new Date(message.time)).toLocaleTimeString();
            message.name = formatName(message);

            var message_el = $j('<li></li>').addClass('chat-message-' + message.type);

            if (self.users.admin[message.user_id] != undefined) {
                message_el.addClass('chat-message-user-admin');
            } else if (message.user_id == self.config.user_id) {
                message_el.addClass('chat-message-user-me');
            }

            if (message.type == 'user') {
                message_el.html('<span class="chat-user"><span class="triangulate"></span><span class="chat-username">'+message.name+'</span><span class="chat-time">'+message.time+'</span></span><span class="chat-message">'+message.body+'</span>');
            } else {
                message_el.html('<span class="chat-user"><span class="triangulate"></span></span><span class="chat-message">'+message.body+'</span>');
            }

            messages_el.append(message_el);
        }
    },

    render_users: function (users) {
        var self = this;

        var chat_users_el = $j('#component'+this.config.component_id+' ul.chat-users');
        chat_users_el.empty();

        var x = 0;

        for (var i in users.active) {
            var user = users.active[i];
			
            //every two add in the dark class chimchim
            if (x%2 == 0) {
                var user_el = $j('<li></li>')
            } else {
                var user_el = $j('<li class="dark"></li>')
            }

            if (self.users.admin[user.user_id] != undefined) {
                user_el.addClass('chat-user-admin');
            } else if (user.user_id == self.config.user_id) {
                user_el.addClass('chat-user-me');
            }

            user_el.text(formatName(user))
            .data('user_id', user.user_id);

            // mute logic
            if (this.config.muted_users == undefined) {
                this.config.muted_users = {};
            }

            if (this.config.muted_users[user.user_id] == 'true') {
                user_el.addClass('chat-user-muted');
            }

            if (this.is_admin() && user.user_id != self.config.user_id) {
                if (this.config.muted_users[user.user_id] != 'true') {
                    var user_mute_el = $j('<a href="#">mute</a>');
                } else {
                    var user_mute_el = $j('<a href="#">unmute</a>');
                }

                user_mute_el.click(function () {
                    var user_id = $j(this).parent().data('user_id');

                    self.mute(user_id);
                }).appendTo(user_el);
            }

            chat_users_el.append(user_el);

            x++;
        }
    },

    render_config: function (config) {
        if (config.state == 'Active') {
            $j('.light').css('background-position', "bottom");
        } else {
            this.render_users({});
            
            $j('.light').css('background-position', "top");

            if(!this.is_admin()) {
                $j('textarea.chat-message-box').attr('disabled', 'true');
            }
        }
    },

    is_admin: function (user_id) {
        if (user_id == undefined) {
            user_id = this.config.user_id;
        }

        return this.users.admin[user_id] != undefined;
    }
};