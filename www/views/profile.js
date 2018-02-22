// Mithril component wrapping around the QRCode for JavaScript library 
var QrCodeWrapper = {
    onupdate: function(vnode) {
        if (vnode.state.data != vnode.attrs.data) {
            while (vnode.dom.firstChild) {
                vnode.dom.removeChild(vnode.dom.firstChild);
            }

            var o = new QRCode(vnode.dom, {
                width : 200,
                height : 200
            });
            o.makeCode(vnode.attrs.data);

            vnode.state.data = vnode.attrs.data;
        }
    },
    view: function() {
        return m("div");
    }
};

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
            User.current.otpUrl ? "Scan this code with a one-time-password authenticator such as Google Authenticator." : null,
            m(QrCodeWrapper, {data: User.current.otpUrl}),
            m("form#twoFactorGenForm.pure-form.pure-form-aligned", {
                    onsubmit: function(e) {
                        e.preventDefault();
                        User.current.password = null;
                        User.current.generateOtpKey = true;
                        User.save().then(function(result) {
                            User.current.otpUrl = result.otpUrl;
                        });
                    }
                },
                User.current.otpUrl ? null : m("fieldset", [
                    m(".pure-controls", [
                        m("button[type=submit].pure-button pure-button-primary", "Generate new key")
                    ])
                ])
            )
        ];
    }
}
