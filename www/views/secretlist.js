var SecretList = {
    oninit: function() {
        Secret.loadList();
    },

    view: function() {
        var table = m("table.pure-table.pure-table-horizontal", [
                m("thead", [
                    m("tr", [
                        m("th", "Title"),
                        m("th", "Username"),
                        m("th", "Modified"),
                    ])
                ]),
                m("tbody", Secret.list.map(function(row) {
                    return m("tr", [
                        m("td", m("a", {href: "/secrets/" + row.id, oncreate: m.route.link}, row.title)),
                        m("td", row.username),
                        m("td", row.modified),
                    ]);
                }))
            ]);

        if (Secret.list.length == 0) {
            table = "No secrets found.";
        }

        return [
            m("h2.content-subhead", "Secrets"),

            m("p", m("a[href=/secrets/new]", {oncreate: m.route.link}, "New Secret")),

            m("form.pure-form.pure-form-aligned", [
                m("input#searchbox[type=text][autofocus]", {oncreate: function(vnode) { setTimeout(function() { vnode.dom.focus(); }, 0); } }),
                " ",
                m("button[type=submit].pure-button", "Search")
            ]),
            m("br"),
            table
        ];
    }
}
