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
                    return m("tr", { style: row.hidden ? "display: none" : "" }, [
                        m("td", m("a", {href: "/secrets/" + row.id, oncreate: m.route.link}, row.title)),
                        m("td", row.username),
                        m("td", row.modified),
                    ]);
                }))
            ]);

        if (Secret.list.length == 0) {
            table = "No secrets found.";
        }

        var search = function() {
            var term = document.getElementById("searchbox").value;

            for (var i = 0; i < Secret.list.length; i++) {
                var row = Secret.list[i];
                row.hidden = false;
                if (row.title.indexOf(term) == -1) {
                    row.hidden = true;
                }
            }
        }

        return [
            m("h2.content-subhead", "Secrets"),

            m("p", m("a[href=/secrets/new]", {oncreate: m.route.link}, "New Secret")),

            m("form.pure-form.pure-form-aligned", [
                m("input#searchbox[type=text][autofocus]", { oncreate: function(vnode) { setTimeout(function() { vnode.dom.focus(); }, 0); } }),
                " ",
                m("button[type=submit].pure-button", { onclick: search }, "Search")
            ]),
            m("br"),
            table
        ];
    }
}
