var Profile = {
    oninit: function(vnode) {
        User.current = {};
        User.load(Session.getUsername());
    },

    view: function() {
        return [
            m("h2.content-subhead", "User profile"),

            m("h3.content-subhead", "Password"),
            m("form.pure-form.pure-form-aligned", {
                    onsubmit: function(e) {
                        e.preventDefault();

                        if (User.current.passwordRepeat != User.current.password) {
                            alert("Password and repeated password must match, but do not match.");
                            return;
                        }

                        User.save().then(function() {
                            m.route.set("/secrets");
                        });
                    }
                },
                m("fieldset", [

                    m(".pure-control-group", [
                        m("label[for=oldPassword]", "Old password"),
                        m("input#oldPassword[type=password]", {
                            oninput: m.withAttr("value", function(value) {
                                User.current.oldPassword = value;
                            }),
                            value: User.current.oldPassword
                        }),
                    ]),

                    m(".pure-control-group", [
                        m("label[for=password]", "New password"),
                        m("input#password[type=password]", {
                            oninput: m.withAttr("value", function(value) {
                                User.current.password = value;
                            }),
                            value: User.current.password
                        }),
                    ]),

                    m(".pure-control-group", [
                        m("label[for=passwordRepeat]", "New password repeated"),
                        m("input#passwordRepeat[type=password]", {
                            oninput: m.withAttr("value", function(value) {
                                User.current.passwordRepeat = value;
                            }),
                            value: User.current.passwordRepeat
                        }),
                    ]),

                    m(".pure-controls", [
                        m("button[type=submit].pure-button pure-button-primary", "Change password")
                    ]),
                ])
            ),


            m("h3.content-subhead", "Two-factor authentication"),
            m("#otpQrCodeDesc"),
            m("#otpQrCode"),
            m("form#twoFactorGenForm.pure-form.pure-form-aligned", {
                    onsubmit: function(e) {
                        e.preventDefault();

                        User.current.generateOtpKey = true;

                        User.save().then(function(result) {

                            // TODO make this more Mithril-friendly

                            var otpQrCode = document.getElementById("otpQrCode");
                            otpQrCode.innerHTML = "";
                            var qrCode = new QRCode(otpQrCode, {
                                width : 200,
                                height : 200
                            });
                            qrCode.makeCode(result.otpUrl);

                            var otpQrCodeDesc = document.getElementById("otpQrCodeDesc");
                            otpQrCodeDesc.innerHTML = "<p>Scan this code with a one-time-password authenticator such as Google Authenticator.</p>";

                            var twoFactorGenForm = document.getElementById("twoFactorGenForm");
                            twoFactorGenForm.innerHTML = "";
                        });
                    }
                },
                m("fieldset", [
                    m(".pure-controls", [
                        m("button[type=submit].pure-button pure-button-primary", "Generate new key")
                    ])
                ])
            )



        ];
    }
}
