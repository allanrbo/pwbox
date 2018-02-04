var GroupForm = {
    oninit: function(vnode) {
        Group.current = {};
        if (vnode.attrs.key != "new") {
            Group.load(vnode.attrs.key);
        }

        User.loadList();
    },

    view: function() {
        var deleteButton = null;
        if (Group.current.modified) {
            deleteButton = m("button.pure-button", {onclick: function(e) {
                e.preventDefault();
                if (confirm("Really delete user group \"" + Group.current.title + "\"?")) {
                    Group.delete().then(function() {
                        m.route.set("/groups");
                    });
                }
            }}, "Delete");
        }

        return [
            m("h2.content-subhead", "Group"),

            m("form.pure-form.pure-form-aligned", {
                    onsubmit: function(e) {
                        e.preventDefault();
                        Group.save().then(function() {
                            m.route.set("/groups");
                        });
                    }
                },
                m("fieldset", [
                    m(".pure-control-group", [
                        m("label[for=Name]", "Name"),
                        m("input#name[type=text]", {
                            oninput: m.withAttr("value", function(value) {
                                Group.current.name = value;
                            }),
                            value: Group.current.name,
                            disabled: Group.current.modified ? true : false
                        }),
                    ]),

                    m(".pure-controls", [
                        m("label", "Members"),
                        User.list.map(function(user) {
                            var checked = false;
                            if (Group.current.members && Group.current.members.indexOf(user.username) > -1) {
                                checked = true;
                            }

                            var isCurrentUser = Session.getUsername() == user.username;
                            if (isCurrentUser) {
                                checked = true;
                            }

                            return m("div",
                                m("label", [
                                    m("input", {
                                        type: "checkbox",
                                        checked: checked,
                                        value: user.username,
                                        disabled: isCurrentUser,
                                        onclick: function() { Group.toggleMember(user.username); }
                                    }),
                                    " " + user.username
                                ])
                            );
                        })
                    ]),

                    m(".pure-controls", [
                        m("button[type=submit].pure-button pure-button-primary", "Save"),
                        " ",
                        deleteButton
                    ]),
                ])
            )
        ];
    }
}
