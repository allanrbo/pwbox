var AdminMenu = {

    controller: function() {
        var self = this;
    },

    view: function(ctrl) {
        return m("#applayout", [
            Templates.navigation(),
            m("#content", [
                m("h2", "Administration"),
                m("p", m("a", {
                    onclick: function() {
                        m.route("/user")
                    }
                }, "Create user")),
                m("p", m("a", {
                    onclick: function() {
                        m.route("/change-my-password")
                    }
                }, "Change my password")),
            ])
        ])
    }

};