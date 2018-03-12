var SecretList = {
    oninit: function() {
        Secret.loadList();
    },

    view: function() {
        var showCopiedToClipboardNotification = function(x, y) {
            var notification = document.createElement("div");
            notification.style =
                "position: fixed;"
                + "left: " + x + "px;"
                + "top: " + y + "px;"
                + "background-color: white;"
                + "padding: 2px;"
                + "border: 1px solid gray;";
            notification.innerText = "Copied to clipboard";
            document.body.appendChild(notification);

            setTimeout(function() {
                notification.style =
                    notification.getAttribute("style")
                    + "visibility: hidden;"
                    + "opacity: 0;"
                    +" transition: visibility 0s 0.5s, opacity 0.5s linear;"

                setTimeout(function() {
                    notification.remove();
                }, 500);
            }, 1500);
        }

        var copyToClipboard = function(value) {
            var input = document.createElement("input");
            input.style = "position: fixed; left: -1000px; top: -1000px";
            document.body.appendChild(input);
            input.value = value;
            input.focus();
            input.select();
            document.execCommand("Copy");
            input.remove();
        }

        function formatDate(date) {
            if (typeof(date) === "string") {
                date = new Date(date);
            }

            function pad(n) {
                var str = "" + n;
                var pad = "00";
                return pad.substring(0, pad.length - str.length) + str;
            }

            return date.getFullYear()
                + "-" + pad(date.getMonth())
                + "-" + pad(date.getDate())
                + " " + pad(date.getHours())
                + ":" + pad(date.getMinutes());
        }

        var table = Secret.listLoaded ? "No secrets found." : "Loading...";

        if (Secret.list.length > 0) {
            table = m("table.pure-table.pure-table-horizontal.secretslist", [
                m("thead", [
                    m("tr", [
                        m("th", {style: ""}, "Title"),
                        m("th", {style: ""}, "Username"),
                        m("th", {style: "width: 5em;"}, "Password"),
                        m("th", {style: "width: 8em;"}, "Modified"),
                    ])
                ]),
                m("tbody", Secret.list.map(function(row) {
                    return m("tr", { style: row.hidden ? "display: none" : "" }, [
                        m("td", m("a", {href: "/secrets/" + row.id, oncreate: m.route.link}, row.title)),
                        m("td", [
                            m("a[href=]", {
                                onclick: function(e) {
                                    copyToClipboard(row.username);
                                    showCopiedToClipboardNotification(
                                        e.clientX,
                                        e.clientY);
                                    return false;
                                }
                            }, m("span.copyicon")),
                            " ",
                            row.username,
                        ]),
                        m("td", [
                            m("a[href=]", {
                                onclick: function(e) {
                                    Secret.loadSync(row.id).then(function() {
                                        copyToClipboard(Secret.current.password);
                                    showCopiedToClipboardNotification(
                                        e.clientX,
                                        e.clientY);
                                    });

                                    return false;
                                }
                            },
                            m("span.copyicon")),
                            " *****",
                        ]),
                        m("td", formatDate(row.modified)),
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
                    m("input#searchbox[type=text][placeholder=Search][autofocus]", {
                        oncreate: function(vnode) {
                            setTimeout(function() {
                                vnode.dom.focus();
                            }, 0);
                        },
                        oninput: function() {
                            search();
                        }
                    })
            ]),
            m("br"),
            table
        ];
    }
}
