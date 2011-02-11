function Chat (config, render_messages, render_users) {
    this.send_data = {
        config: {},
        messages: {}
    };

    this.config = config;
    this.render_messages = render_messages;
    this.render_users = render_users;

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

        this.render_messages(data.messages);
        this.render_users(data.users);
    },

    send: function (data) {
        this.render_messages(data.messages);
        $j.extend(this.send_data, data);
    }
}