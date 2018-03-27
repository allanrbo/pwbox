var SecretForm = {
    oninit: function(vnode) {
        Secret.current = {};
        if (vnode.attrs.key != "new") {
            Secret.load(vnode.attrs.key);
        }

        Group.loadList();
        User.loadList();
    },

    view: function(vnode) {
        var loading = false;
        if (vnode.attrs.key != "new") {
            if (!Secret.currentLoaded || !Group.listLoaded || !User.listLoaded) {
                loading = true;
            }
        } else {
            if (!Group.listLoaded || !User.listLoaded) {
                loading = true;
            }
        }

        var deleteButton = null;
        if (Secret.current.id) {
            deleteButton = m("button.pure-button", {onclick: function(e) {
                e.preventDefault();
                if (confirm("Really delete secret \"" + Secret.current.title + "\"?")) {
                    Secret.delete().then(function() {
                        m.route.set("/secrets");
                    });
                }
            }}, "Delete");
        }

        var availableGroups = [];
        for (var i = 0; i < Group.list.length; i++) {
            if (Group.list[i].name != "Administrators") {
                availableGroups.push(Group.list[i]);
            }
        }

        var shareWithUserGroups = null;
        if (availableGroups.length > 0) {
            shareWithUserGroups = m(".pure-controls", [
                m("label", "Shared with user groups"),
                availableGroups.map(function(group) {
                    var checked = false;
                    if (Secret.current.groups && Secret.current.groups.indexOf(group.name) > -1) {
                        checked = true;
                    }

                    return m("div",
                        m("label", [
                            m("input", {
                                type: "checkbox",
                                checked: checked,
                                value: group.name,
                                onclick: function() { Secret.toggleGroup(group.name); }
                            }),
                            " " + group.name
                        ])
                    );
                })
            ]);
        }

        var owner = null;
        if (User.list.length > 1) {
            owner = m(".pure-control-group", [
                m("label[for=Owner]", "Owner"),
                m("select#owner", {
                    onchange: m.withAttr("value", function(value) {
                        Secret.current.owner = value;
                    })},
                    User.list.map(function(user) {
                        var selected = "";
                        if ((!Secret.current.owner && user.username == Session.getUsername()) || (Secret.current.owner == user.username)) {
                            selected = "selected";
                        }

                        return m("option", {
                            value: user.username,
                            selected: selected
                        }, user.username);
                    })
                )
            ]);
        }

        var form = m("form.pure-form.pure-form-aligned",
            {
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
                    m("input#title[type=text][autocomplete=off]", {
                        oninput: m.withAttr("value", function(value) {
                            Secret.current.title = value;
                        }),
                        value: Secret.current.title
                    }),
                ]),

                m(".pure-control-group", [
                    m("label[for=username]", "Username"),
                    m("input#username[type=text][autocomplete=off]", {
                        oninput: m.withAttr("value", function(value) {
                            Secret.current.username = value;
                        }),
                        value: Secret.current.username
                    }),
                ]),

                m(".pure-control-group", [
                    m("label[for=password]", "Password"),
                    m("input#password[type=text][autocomplete=off]", {
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

                owner,

                shareWithUserGroups,

                m(".pure-controls", [
                    m("button[type=submit].pure-button pure-button-primary", "Save"),
                    " ",
                    deleteButton
                ]),
            ])
        );

        return [
            m("h2.content-subhead", "Secret"),
            loading ? "Loading..." : form
        ];
    }
}
