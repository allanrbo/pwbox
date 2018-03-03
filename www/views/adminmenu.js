var AdminMenu = {
    view: function() {
        var isAdmin = false;
        if (Session.getProfile()) {
            isAdmin = Session.getProfile().groupMemberships.indexOf("Administrators") != -1;
        }

        return [
            m("h2.content-subhead", "Administration"),
            m("p", m("a[href=/admin/profile]", {oncreate: m.route.link}, "My user profile")),
            isAdmin ? m("p", m("a[href=/admin/users]", {oncreate: m.route.link}, "Users")) : null,
            isAdmin ? m("p", m("a[href=/admin/groups]", {oncreate: m.route.link}, "User groups")) : null,
            isAdmin ? m("p", m("a[href=/admin/backuprestore]", {oncreate: m.route.link}, "Backup and restore")) : null,
        ];
    }
}
