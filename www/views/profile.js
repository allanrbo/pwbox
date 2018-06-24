var Profile = {
    oninit: function(vnode) {
        User.current = {};
        User.load(Session.getUsername());

        UserChangePassword.current = {};
    },

    view: function() {
        return [
            m("h2.content-subhead", "User profile"),

            m("h3.content-subhead", "Password"),
            m("form.pure-form.pure-form-aligned", {
                    onsubmit: function(e) {
                        e.preventDefault();

                        if (UserChangePassword.current.passwordRepeat != UserChangePassword.current.password) {
                            alert("Password and repeated password must match, but do not match.");
                            return;
                        }

                        UserChangePassword.save().then(function() {
                            alert("Password updated");
                            UserChangePassword.current.oldPassword = "";
                            UserChangePassword.current.password = "";
                            UserChangePassword.current.passwordRepeat = "";
                        });
                    }
                },
                m("fieldset", [

                    m(".pure-control-group", [
                        m("label[for=oldPassword]", "Old password"),
                        m("input#oldPassword[type=password]", {
                            autocomplete: "new-password",
                            oninput: m.withAttr("value", function(value) {
                                UserChangePassword.current.oldPassword = value;
                            }),
                            value: UserChangePassword.current.oldPassword
                        }),
                    ]),

                    m(".pure-control-group", [
                        m("label[for=password]", "New password"),
                        m("input#password[type=password]", {
                            autocomplete: "new-password",
                            oninput: m.withAttr("value", function(value) {
                                UserChangePassword.current.password = value;
                            }),
                            value: UserChangePassword.current.password
                        }),
                    ]),

                    m(".pure-control-group", [
                        m("label[for=passwordRepeat]", "New password repeated"),
                        m("input#passwordRepeat[type=password]", {
                            autocomplete: "new-password",
                            oninput: m.withAttr("value", function(value) {
                                UserChangePassword.current.passwordRepeat = value;
                            }),
                            value: UserChangePassword.current.passwordRepeat
                        }),
                    ]),

                    m(".pure-controls", [
                        m("button[type=submit].pure-button pure-button-primary", "Change password")
                    ]),
                ])
            ),


            m("h3.content-subhead", "Two-factor authentication"),
            !UserChangeOtpKey.current.otpUrl ?
                "Before generating a key, please ensure you have a one-time password authenticator app installed, such as Google Authenticator."
                : "Scan this code with a one-time password authenticator such as Google Authenticator.",
            !UserChangeOtpKey.current.otpUrl ?
                null
                : m(QrCodeWrapper, {data: UserChangeOtpKey.current.otpUrl}),
            m("form#twoFactorGenForm.pure-form.pure-form-aligned",
                (User.current.otpEnabled && !UserChangeOtpKey.current.otpUrl) ? "Generating new keys will invalidate your existing key in your authenticator app as well as any emergency one-time passwords, and generate new ones." : null,
                (!User.current.otpEnabled && !UserChangeOtpKey.current.otpUrl) ? m("fieldset", [
                    m(".pure-controls", [
                        m("button.pure-button pure-button-primary", {
                            onclick: function() {
                                UserChangeOtpKey.save().then(function(result) {
                                    UserChangeOtpKey.current = result;
                                    Session.refreshProfile();
                                });
                            }
                        }, "Enable two-factor authentication")
                    ])
                ]) : null,
                (User.current.otpEnabled && !UserChangeOtpKey.current.otpUrl) ? m("fieldset", [
                    m(".pure-controls", [
                        m("button[type=submit].pure-button pure-button-primary", {
                            onclick: function() {
                                if (!confirm("This will invalidate your existing key in your authenticator app as well as any emergency one-time passwords, and generate new ones. Are you sure?")) {
                                    return;
                                }

                                UserChangeOtpKey.save().then(function(result) {
                                    UserChangeOtpKey.current = result;
                                    Session.refreshProfile();
                                });
                            }
                        },
                        "Generate new two-factor authentication keys")
                    ])
                ]) : null,
                !UserChangeOtpKey.current.emergencyPasswords ? null : [
                    m("p", "Emergency one-time use passwords:"),
                    m("pre", UserChangeOtpKey.current.emergencyPasswords.join("\n")),
                    m("p", "We suggest to note these emergency passwords on paper in a secure location, for use if the phone with the authenticator app gets lost. Each password can only be used once."),
                ],
                User.current.otpEnabled ? m("fieldset", [
                    m(".pure-controls", [
                        m("button.pure-button pure-button-primary",  {
                            onclick: function() {
                                if (!confirm("Are you sure you want to disable two-factor authentication?")) {
                                    return;
                                }

                                UserChangeOtpKey.disable().then(function() {
                                    UserChangeOtpKey.current = {};
                                    Session.refreshProfile();
                                });
                            }
                        }, "Disable two-factor authentication")
                    ])
                ]) : null
            )
        ];
    }
}
