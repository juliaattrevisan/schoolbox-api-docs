function Chat (config) {
    this.send_data = {
        config: {},
        messages: {}
    };

    this.config = config;
    this.interval();
}

Chat.prototype = {
    interval: function () {
        var self = this;

        this.interval_ref = setInterval(function () {
            self.update();
        }, 2000);
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

        this.render_users(data.users);
        this.render_messages(data.messages);
    },

    send: function () {
        var message = $j('#component'+this.config.component_id+' textarea[name="message"]');


			var data = {
				messages: {
					0: {
						title: chat_obj.config.name.title,
						firstname: chat_obj.config.name.firstname,
						lastname: chat_obj.config.name.lastname,
						type: 'user',
						time: (new Date()).toString(),
						body: message.val()
					}
				}
			}
	
			message.val('');
	
			this.render_messages(data.messages);
			$j.extend(this.send_data, data);
		

        return false;
    },

    mute: function (user_id) {
        
    },

    render_messages: function (messages) {
        var messages_el = $j('#component'+this.config.component_id+' ul.chat-messages');

        for (var i in messages) {
            current_message = messages[i];

            message_el = $j('<li></li>')

            current_message.time = (new Date(current_message.time)).toLocaleTimeString();

            if (current_message.type == 'user') {
                current_message.name = formatName(current_message);
                message_el.html('<span class="chat-user"><span class="triangulate"></span><span class="chat-username">'+current_message.name + '</span><span class="chat-time">' + current_message.time + '</span></span><span class="chat-message">'+current_message.body+'</span>');
            } else {
                message_el.html('<span class="chat-user"><span class="triangulate"></span></span><span class="chat-message">'+current_message.body+'</span>');
            }

            message_el.addClass('chat-message-' + current_message.type);
            messages_el.append(message_el);
			
        }
    },

    render_users: function (users) {
        chat_users_el = $j('#component'+this.config.component_id+' ul.chat-users');
        chat_users_el.empty();

        for (var i = 0; i < users['active'].length; i++) {
            current_user = users['active'][i];
			
			//every two add in the dark class chimchim
			if (i%2 == 0)  {
           		user_el = $j('<li></li>')
			} else {
				user_el = $j('<li class="dark"></li>')
			}
			
               user_el.text(formatName(current_user))
                .data('user_id', current_user.user_id);

            if (this.config.muted_users == undefined || this.config.muted_users[current_user.user_id] != true) {
                user_el.removeClass('chat-user-muted');
                user_mute_el = $j('<a href="#">mute</a>');
            } else {
                user_el.addClass('chat-user-muted');
                user_mute_el = $j('<a href="#">unmute</a>');
            }

            var self = this;

            user_mute_el.click(function () {
                user_id = $j(this).parent().data('user_id');

                self.mute(user_id);
            }).appendTo(user_el);

            chat_users_el.append(user_el);
        }
    }
}