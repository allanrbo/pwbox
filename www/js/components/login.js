var Login = {

    controller: function() {

        var self = this;

        this.username = m.prop("");
        this.password = m.prop("");
        this.errorMessage = m.prop("");

        this.login = function() {
            Session.login(this.username(), this.password()).then(function(data) {
                self.errorMessage("")
                m.route("/secrets");
            }, function(a) {
                self.errorMessage(a.message);
            });
        }.bind(this);
    },

    view: function(ctrl) {
        return m(".login-form", [
            m("h1", "PwBox login"),
            ctrl.errorMessage() ? m("div.error-message", ctrl.errorMessage()) : "",
            m("div.pure-form.pure-form-aligned", [
                m("fieldset", [
                    m(".pure-control-group", [
                        m("label", {
                            "for": "username"
                        }, "Username"),
                        m("input[id='username'][type='text']", {
                            value: ctrl.username(),
                            oninput: m.withAttr("value", ctrl.username)
                        })
                    ]),
                    m(".pure-control-group", [
                        m("label", {
                            "for": "password"
                        }, "Password"),
                        m("input", {
                            id: "password",
                            type: "password",
                            value: ctrl.password(),
                            oninput: m.withAttr("value", ctrl.password)
                        })
                    ]),
                    m(".pure-controls", [
                        m("button.pure-button.pure-button-primary", {
                            onclick: ctrl.login
                        }, "Log in")
                    ])
                ])
            ])
        ])
    }

};