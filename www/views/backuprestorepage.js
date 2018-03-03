var BackupRestorePage = {
    oninit: function(vnode) {
    },

    view: function() {
        return [
            m("h2.content-subhead", "Backup and restore"),

            m("p", m("a[href=]", {
                onclick: function() {
                    BackupRestore.getCsv().then(function(result) {
                        var blob = new Blob([result], {type: 'text/csv'});
                        var a = document.createElement("a");
                        a.style = "display: none";
                        document.body.appendChild(a);
                        var url = window.URL.createObjectURL(blob);
                        a.href = url;
                        a.download = "secrets.csv";
                        a.click();
                        window.URL.revokeObjectURL(url);
                        a.remove();
                    });

                    return false;
                }
            }, "Export all secrets in plain text CSV")),

            m("p", m("a[href=]", {
                onclick: function() {
                    var input = document.createElement("input");
                    input.type = "file";
                    input.style = "display: none";
                    input.onchange = function(inputEvent) {
                        var reader = new FileReader();
                        reader.onload = function (readerEvent) {
                            BackupRestore.putCsv(readerEvent.target.result).then(function() {
                                alert("Successfully imported CSV");
                            });
                        }

                        reader.readAsText(inputEvent.target.files[0]);
                    };

                    document.body.appendChild(input);
                    input.click();
                    input.remove();

                    return false;
                }
            }, "Import from plain text CSV")),


            m("p", m("a[href=/admin]", {oncreate: m.route.link}, "Export all secrets encrypted in a tar")),
            m("p", m("a[href=/admin]", {oncreate: m.route.link}, "Import encrypted secrets from a tar")),
            m("p", m("a[href=/admin]", {oncreate: m.route.link}, "Export all users and their encryption keys to a tar.gz")),
            m("p", m("a[href=/admin]", {oncreate: m.route.link}, "Import users and their encryption keys from a tar.gz")),
        ];
    }
}
