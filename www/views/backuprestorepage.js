var BackupRestorePage = {
    oninit: function(vnode) {
    },

    view: function() {
        return [
            m("h2.content-subhead", "Backup and restore"),

            m("h3.content-subhead", "Plain text CSV file"),

            m("p", m("a[href=]", {
                onclick: function() {
                    BackupRestore.getCsv().then(function(result) {
                        var blob = new Blob([result], {type: "text/csv"});
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

            m("p", [
                m("a[href=]", {
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
                }, "Import and merge from plain text CSV"),
                m("br"),
                "Merges entries where the pwboxId column matches an existing entry. Creates new entries for all other rows.",
            ]),

            m("h3.content-subhead", "Encrypted tar file"),

            m("p", m("a[href=]", {
                onclick: function() {
                    BackupRestore.getTarSecrets().then(function(result) {
                        var blob = new Blob([result], {type: "application/tar"});
                        var a = document.createElement("a");
                        a.style = "display: none";
                        document.body.appendChild(a);
                        var url = window.URL.createObjectURL(blob);
                        a.href = url;
                        a.download = "secrets.tar";
                        a.click();
                        window.URL.revokeObjectURL(url);
                        a.remove();
                    });

                    return false;
                }
            }, "Export all secrets encrypted in a tar")),

            m("p", [
                m("a[href=]", {
                    onclick: function() {
                        var input = document.createElement("input");
                        input.type = "file";
                        input.style = "display: none";
                        input.onchange = function(inputEvent) {
                            var reader = new FileReader();
                            reader.onload = function (readerEvent) {
                                BackupRestore.putTarSecrets(readerEvent.target.result).then(function() {
                                    alert("Successfully imported and replaced secrets from tar of encrypted secrets");
                                });
                            }

                            reader.readAsText(inputEvent.target.files[0]);
                        };

                        document.body.appendChild(input);
                        input.click();
                        input.remove();

                        return false;
                    }
                }, "Import and replace secrets from tar of encrypted secrets")
            ]),

            m("p", m("a[href=/admin]", {oncreate: m.route.link}, "Export all users and their encryption keys and groups to a tar")),
            m("p", m("a[href=/admin]", {oncreate: m.route.link}, "Import users and their encryption keys and groups from a tar")),
        ];
    }
}
