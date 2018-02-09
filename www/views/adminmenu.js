var AdminMenu = {
    view: function() {
        return [
            m("h2.content-subhead", "Administration"),
            m("p", m("a[href=/user/new]", {oncreate: m.route.link})),
            m("p", m("a[href=/admin/changepassword]", {oncreate: m.route.link}, "Change my password")),
            m("p", m("a[href=/admin/newuser]", {oncreate: m.route.link}, "Create new user")),
            m("p", m("a[href=/admin/groups]", {oncreate: m.route.link}, "User groups")),
        ];
    }
}
