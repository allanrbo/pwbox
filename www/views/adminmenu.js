var AdminMenu = {
    view: function() {
        return [
            m("h2.content-subhead", "Administration"),
            m("p", m("a[href=/admin/users]", {oncreate: m.route.link}, "Users")),
            m("p", m("a[href=/admin/groups]", {oncreate: m.route.link}, "User groups")),
            m("p", m("a[href=/admin/backuprestore]", {oncreate: m.route.link}, "Backup and restore")),
            m("p", m("a[href=/admin/softwareupdates]", {oncreate: m.route.link}, "Software updates")),
        ];
    }
}
