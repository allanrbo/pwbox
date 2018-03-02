var UserForm = {
    oninit: function(vnode) {
        User.current = {};
        if (vnode.attrs.key != "new") {
            User.load(vnode.attrs.key);
        }
    },

    view: function() {
        return [
            m("h2.content-subhead", "User"),

            m("form.pure-form.pure-form-aligned", {
                    onsubmit: function(e) {
                        e.preventDefault();
                        User.save().then(function() {
                            m.route.set("/admin/users");
                        });
                    }
                },
                m("fieldset", [
                    m(".pure-control-group", [
                        m("label[for=username]", "Username"),
                        m("input#username[type=text][autocomplete=off]", {
                            oninput: m.withAttr("value", function(value) {
                                User.current.username = value;
                            }),
                            value: User.current.username,
                            disabled: User.current.modified ? true : false
                        }),
                    ]),

                    User.current.modified ? null : m(".pure-control-group", [
                        m("label[for=password]", "Password"),
                        m("input#password[type=text][autocomplete=off]", {
                            oninput: m.withAttr("value", function(value) {
                                User.current.password = value;
                            }),
                            value: User.current.password
                        }),
                    ]),

                    m(".pure-control-group", [
                        m("label[for=lockedOut]", "Locked out"),
                        m("input#lockedOut[type=checkbox]", {
                            onclick: m.withAttr("checked", function(checked) {
                                User.current.lockedOut = checked;
                            }),
                            checked: User.current.lockedOut
                        }),
                    ]),

                    m(".pure-controls", [
                        m("button[type=submit].pure-button pure-button-primary", "Save")
                    ]),
                ])
            )
        ];
    }
}
