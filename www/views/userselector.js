var UserSelector = {
    oninit: function(vnode) {
        User.loadList();
    },

    view: function() {
        return [
            m(".pure-controls", [
                m("label", "Shared with"),
                User.list.map(function(user) {
                    var checked = false;
                    if (Secret.current.recipients && Secret.current.recipients.indexOf(user.username) > -1) {
                        checked = true;
                    }
                    if (Session.getUsername() == user.username) {
                        checked = true;
                    }

                    return m("div",
                        m("label", [
                            m("input", {
                                type: "checkbox",
                                checked: checked,
                                value: user.username,
                                onclick: function() { Secret.toggleRecipient(user.username); }
                            }),
                            " " + user.username
                        ])
                    );
                })
            ])
        ];
    }
}
