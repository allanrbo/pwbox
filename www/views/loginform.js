var LoginForm = {
    oninit: function() {
        Session.current = {};

        if (Session.getSessionRemainingTimeSecs() > 0) {
            m.route.set("/secrets");
        }
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

                // Unused dummy decoy password field, to prevent browser auto save
                m("input#password2[type=password]", {style: "display: none;", tabindex: -1}),

                m("fieldset", [
                    m(".pure-control-group", [
                        m("label[for=username]", "Username"),
                        m("input#username[type=text]", {
                            oncreate: function(vnode) {
                                setTimeout(function() {
                                    vnode.dom.focus();
                                }, 0);
                            },
                            oninput: m.withAttr("value", function(value) {
                                Session.current.username = value;
                            }),
                            value: Session.current.username
                        }),
                    ]),

                    m(".pure-control-group", [
                        m("label[for=password]", "Password"),
                        m("input#password[type=password]", {
                            autocomplete: "new-password",
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
