var SecretList = {
    oninit: function() {
        Secret.loadList();
    },

    view: function() {
        return [
            m("h2.content-subhead", "Secrets"),

            m("form.pure-form.pure-form-aligned", [
                m("input#searchbox[type=text][autofocus]", {oncreate: function(vnode) { setTimeout(function() { vnode.dom.focus(); }, 0); } }),
                " ",
                m("button[type=submit].pure-button", "Search")
            ]),
            m("br"),
            m("table.pure-table.pure-table-horizontal", [
                m("thead", [
                    m("tr", [
                        m("th", "Title")
                    ])
                ]),
                m("tbody", Secret.list.map(function(row) {
                    return m("tr", [
                        m("td", m("a", {href: "/secrets/" + row.id, oncreate: m.route.link}, row.title))
                    ]);
                }))
            ]),
        ];
    }
}
