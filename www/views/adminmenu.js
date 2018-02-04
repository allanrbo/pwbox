var AdminMenu = {
    view: function() {
        return [
            m("h2.content-subhead", "Administration"),
            m("p", m("a[href=/user/new]", {oncreate: m.route.link})),
            m("p", m("a[href=/changepassword]", {oncreate: m.route.link}, "Change my password")),
            m("p", m("a[href=/newuser]", {oncreate: m.route.link}, "Create new user")),
            m("p", m("a[href=/groups/new]", {oncreate: m.route.link}, "Create new user group")),
            m("p", m("a[href=/groups]", {oncreate: m.route.link}, "List user groups")),
        ];
    }
}
