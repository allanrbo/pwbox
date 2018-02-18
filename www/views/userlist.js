var UserList = {
    oninit: function() {
        User.loadList();
    },

    view: function() {
        var table = m("table.pure-table.pure-table-horizontal", [
                m("thead", [
                    m("tr", [
                        m("th", "Name"),
                    ])
                ]),
                m("tbody", User.list.map(function(row) {
                    return m("tr", [
                        m("td", m("a", {href: "/admin/users/" + row.username, oncreate: m.route.link}, row.username)),
                    ]);
                }))
            ]);

        if (User.list.length == 0) {
            table = "No users found.";
        }

        return [
            m("h2.content-subhead", "Users"),
            m("p", m("a[href=/admin/users/new]", {oncreate: m.route.link}, "New user")),
            table
        ];
    }
}
