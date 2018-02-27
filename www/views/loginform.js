var LoginForm = {
    oninit: function() {
        Session.current = {};
    },

    view: function() {
        return [
            m("form.pure-form.pure-form-aligned.login-form", {
                    onsubmit: function(e) {
                        e.preventDefault();
                        Session.login()
                        .then(function() {
                            m.route.set("/secrets");
                        })
                        .catch(function() {
                            Session.current.loggingIn = false;
                        });
                        Session.current.loggingIn = true;
                    }
                },
                m("fieldset", [
                    m(".pure-control-group", [
                        m("label[for=username]", "Username"),
                        m("input#username[type=text]", {
                            oninput: m.withAttr("value", function(value) {
                                Session.current.username = value;
                            }),
                            value: Session.current.username
                        }),
                    ]),

                    m(".pure-control-group", [
                        m("label[for=password]", "Password"),
                        m("input#password[type=password]", {
                            oninput: m.withAttr("value", function(value) {
                                Session.current.password = value;
                            }),
                            value: Session.current.password
                        }),
                    ]),

                    m(".pure-control-group", [
                        m("label[for=otp]", "Two-factor password"),
                        m("input#otp[type=text]", {
                            oninput: m.withAttr("value", function(value) {
                                Session.current.otp = value;
                            }),
                            value: Session.current.otp
                        }),
                    ]),

                    m(".pure-controls", m("button[type=submit].pure-button pure-button-primary",
                        {disabled: !Session.current.loggingIn ? "" : "disabled"}, Session.current.loggingIn ? "Logging in..." : "Log in"))
                ])
            )
        ];
    }
}
