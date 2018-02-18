var GroupList = {
    oninit: function() {
        Group.loadList();
    },

    view: function() {
        var table = m("table.pure-table.pure-table-horizontal", [
                m("thead", [
                    m("tr", [
                        m("th", "Name"),
                        m("th", "Members")
                    ])
                ]),
                m("tbody", Group.list.map(function(row) {
                    return m("tr", [
                        m("td", m("a", {href: "/admin/groups/" + row.name, oncreate: m.route.link}, row.name)),
                        m("td", row.members.join(", ")),
                    ]);
                }))
            ]);

        if (Group.list.length == 0) {
            table = "No groups found.";
        }

        return [
            m("h2.content-subhead", "Groups"),
            m("p", m("a[href=/admin/groups/new]", {oncreate: m.route.link}, "New group")),
            table
        ];
    }
}
