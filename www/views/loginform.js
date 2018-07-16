var LoginForm = {
    oninit: function() {
        Session.current = {};

        if (Session.getSessionRemainingTimeSecs() > 0) {
            m.route.set("/secrets");
            return;
        }

        if (Session.getTrustedDevice()) {
            Session.current.trustedDeviceToken = Session.getTrustedDevice().token;
            Session.current.alreadyTrustedDevice = true;
            Session.current.trustDevice = 1;
            Session.current.username = Session.getUsername();
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
                                if (!Session.current.alreadyTrustedDevice) {
                                    setTimeout(function() {
                                        vnode.dom.focus();
                                    }, 0);
                                }
                            },
                            oninput: m.withAttr("value", function(value) {
                                Session.current.username = value;
                            }),
                            value: Session.current.username,
                            readonly: Session.current.alreadyTrustedDevice,
                            disabled: !Session.current.loggingIn ? "" : "disabled"
                        }),
                    ]),

                    m(".pure-control-group", [
                        m("label[for=password]", "Password"),
                        m("input#password[type=password]", {
                            oncreate: function(vnode) {
                                if (Session.current.alreadyTrustedDevice) {
                                    setTimeout(function() {
                                        vnode.dom.focus();
                                    }, 0);
                                }
                            },
                            autocomplete: "new-password",
                            oninput: m.withAttr("value", function(value) {
                                Session.current.password = value;
                            }),
                            value: Session.current.password,
                            disabled: !Session.current.loggingIn ? "" : "disabled"
                        }),
                    ]),

                    m(".pure-control-group", [
                        m("label[for=trustDevice]", "Trust device"),
                        m("input#trustDevice[type=checkbox]", {
                            onclick: m.withAttr("checked", function(checked) {
                                Session.current.trustDevice = checked;

                                if (Session.current.alreadyTrustedDevice && !checked) {
                                    Session.removeTrustedDevice();
                                    Session.current.trustedDeviceToken = null;
                                    Session.current.alreadyTrustedDevice = false;
                                    Session.current.username = "";
                               }
                            }),
                            checked: Session.current.trustDevice,
                            disabled: !Session.current.loggingIn ? "" : "disabled"
                        }),
                    ]),

                    Session.current.alreadyTrustedDevice || !Session.current.trustDevice ? null : m(".pure-control-group", [
                        m("label[for=trustedDeviceName]", "Device name"),
                        m("input#trustedDeviceName[type=text]", {
                            oninput: m.withAttr("value", function(value) {
                                Session.current.trustedDeviceName = value;
                            }),
                            value: Session.current.trustedDeviceName,
                            disabled: !Session.current.loggingIn ? "" : "disabled"
                        }),
                    ]),

                    Session.current.alreadyTrustedDevice ? null : m(".pure-control-group", [
                        m("label[for=otp]", "Two-factor password"),
                        m("input#otp[type=text]", {
                            oninput: m.withAttr("value", function(value) {
                                Session.current.otp = value;
                            }),
                            value: Session.current.otp,
                            disabled: !Session.current.loggingIn ? "" : "disabled"
                        }),
                    ]),

                    m(".pure-controls", m("button[type=submit].pure-button pure-button-primary",
                        { disabled: !Session.current.loggingIn ? "" : "disabled" }, Session.current.loggingIn ? "Logging in..." : "Log in"))
                ])
            )
        ];
    }
}
