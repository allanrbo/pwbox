var SecretList = {
    oninit: function() {
        Secret.loadList();
    },

    view: function() {
        var table = table = "No secrets found.";

        if (Secret.list.length > 0) {
            table = m("table.pure-table.pure-table-horizontal", [
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
                        m("td", [
                            m("a[href=]", {
                                onclick: function() {
                                    var input = document.createElement("input");
                                    input.style = "position: absolute; left: -1000px; top: -1000px";
                                    document.body.appendChild(input);
                                    input.value = row.username;
                                    input.select();
                                    document.execCommand("Copy");
                                    input.remove();
                                    return false;
                                }
                            }, m("span.copyicon")),
                            row.username,


                        ]),
                        m("td", row.modified),
                    ]);
                }))
            ]);
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

            m("form.pure-form.pure-form-aligned", {
                onsubmit: function(e) {
                    e.preventDefault();
                    search();
                }}, [
                m("input#searchbox[type=text][placeholder=Search][autofocus]", { oncreate: function(vnode) { setTimeout(function() { vnode.dom.focus(); }, 0); } }),
                " ",
                m("button[type=submit].pure-button", { onclick: search }, "Search")
            ]),
            m("br"),
            table
        ];
    }
}
