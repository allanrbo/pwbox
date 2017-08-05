var SecretForm = {
    oninit: function(vnode) {
        console.log("hi1");
        Secret.current = {};
        if (vnode.attrs.key != "new") {
            Secret.load(vnode.attrs.key);
        }
    },

    view: function() {
        return [
            m("h2.content-subhead", "Edit secret"),

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

                    m(".pure-controls", m("button[type=submit].pure-button pure-button-primary", "Save"))
                ])
            )
        ];
    }
}