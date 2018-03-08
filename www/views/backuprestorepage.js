var BackupRestorePage = {
    oninit: function(vnode) {
    },

    view: function() {

        var makeFileDownloader = function(downloadFunc, outfilename, mimetype) {
            return function() {
                downloadFunc().then(function(result) {
                    var blob = new Blob([result], {type: mimetype});
                    var a = document.createElement("a");
                    a.style = "display: none";
                    document.body.appendChild(a);
                    var url = window.URL.createObjectURL(blob);
                    a.href = url;
                    a.download = outfilename;
                    a.click();
                    window.URL.revokeObjectURL(url);
                    a.remove();
                });

                return false;
            }
        };

        var makeFileUploader = function(uploaderFunc, successMsg) {
            return function() {
                var input = document.createElement("input");
                input.type = "file";
                input.style = "display: none";
                input.onchange = function(inputEvent) {
                    var reader = new FileReader();
                    reader.onload = function (readerEvent) {
                        uploaderFunc(readerEvent.target.result).then(function() {
                            alert(successMsg);
                        });
                    }

                    reader.readAsArrayBuffer(inputEvent.target.files[0]);
                };

                document.body.appendChild(input);
                input.click();
                input.remove();

                return false;
            }
        };



        return [
            m("h2.content-subhead", "Backup and restore"),

            m("h3.content-subhead", "Plain text CSV file"),

            m("p", m("a[href=]", {
                onclick: makeFileDownloader(BackupRestore.getCsv, "secrets.csv", "text/csv")
            }, "Export all secrets in plain text CSV")),

            m("p", [
                m("a[href=]", {
                    onclick: makeFileUploader(BackupRestore.putCsv, "Successfully imported CSV.")
                }, "Import and merge from plain text CSV"),
                m("br"),
                "Export first to learn the format. Import merges entries where the pwboxId column matches an existing entry. Creates new entries for all other rows.",
            ]),

            m("h3.content-subhead", "Encrypted tar file"),

            m("p", m("a[href=]", {
                onclick: makeFileDownloader(BackupRestore.getTarSecrets, "secrets.tar", "application/tar")
            }, "Export all secrets encrypted in a tar")),

            m("p", [
                m("a[href=]", {
                    onclick: makeFileUploader(BackupRestore.putTarSecrets, "Successfully imported and replaced secrets from tar of encrypted secrets.")
                }, "Import and replace secrets from tar of encrypted secrets")
            ]),

            m("p", m("a[href=]", {
                onclick: makeFileDownloader(BackupRestore.getTarUsers, "users.tar", "application/tar")
            }, "Export all users and their encryption keys and groups to a tar")),

            m("p", [
                m("a[href=]", {
                    onclick: makeFileUploader(BackupRestore.putTarUsers, "Successfully imported users and their encryption keys and groups from given tar.")
                }, "Import users and their encryption keys and groups from a tar")
            ])
        ];
    }
}
