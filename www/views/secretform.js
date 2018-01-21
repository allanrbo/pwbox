var SecretForm = {
    oninit: function(vnode) {
        Secret.current = {};
        if (vnode.attrs.key != "new") {
            Secret.load(vnode.attrs.key);
        }
    },

    view: function() {
        return [
            m("h2.content-subhead", "Secret"),

            m("form.pure-form.pure-form-aligned", {
                    onsubmit: function(e) {
                        e.preventDefault();
                        Secret.save().then(function() {
                            m.route.set("/secrets");
                        });
                    }
                },
                m("fieldset", [
                    m(".pure-control-group", [
                        m("label[for=title]", "Title"),
                        m("input#title[type=text]", {
                            oninput: m.withAttr("value", function(value) {
                                Secret.current.title = value;
                            }),
                            value: Secret.current.title
                        }),
                    ]),

                    m(".pure-control-group", [
                        m("label[for=username]", "Username"),
                        m("input#username[type=text]", {
                            oninput: m.withAttr("value", function(value) {
                                Secret.current.username = value;
                            }),
                            value: Secret.current.username
                        }),
                    ]),

                    m(".pure-control-group", [
                        m("label[for=password]", "Password"),
                        m("input#password[type=text]", {
                            oninput: m.withAttr("value", function(value) {
                                Secret.current.password = value;
                            }),
                            value: Secret.current.password
                        }),
                    ]),

                    m(".pure-control-group", [
                        m("label[for=notes]", "Notes"),
                        m("textarea#notes", {
                            oninput: m.withAttr("value", function(value) {
                                Secret.current.notes = value;
                            }),
                            value: Secret.current.notes,
                            cols: 22,
                            rows: 6,
                        }),
                    ]),


                    m(UserSelector),

                    m(".pure-controls", [
                        m("button[type=submit].pure-button pure-button-primary", "Save"),
                        " ",
                        m("button.pure-button", {onclick: function(e) {
                            e.preventDefault();
                            if (confirm("Really delete secret \"" + Secret.current.title + "\"?")) {
                                Secret.delete().then(function() {
                                    m.route.set("/secrets");
                                });
                            }
                        }}, "Delete"),
                    ]),
                ])
            )
        ];
    }
}
