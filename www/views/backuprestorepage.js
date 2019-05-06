var BackupRestorePage = {
    oninit: function(vnode) {
        BackupRestore.loadBackupTokenStatus();

        BackupRestorePage.showspinner = false;
    },

    view: function() {

        var makeFileDownloader = function(downloadFunc, outfilename, mimetype) {
            return function() {
                BackupRestorePage.showspinner = true;

                downloadFunc()
                    .catch(function(e) {
                        BackupRestorePage.showspinner = false;
                        throw e;
                    })
                    .then(function(result) {
                        var blob = new Blob([result], {type: mimetype});
                        if(window.navigator.msSaveOrOpenBlob) {
                            window.navigator.msSaveOrOpenBlob(blob, outfilename);
                        } else {
                            var a = document.createElement("a");
                            a.style = "display: none";
                            document.body.appendChild(a);
                            var url = window.URL.createObjectURL(blob);
                            a.href = url;
                            a.download = outfilename;
                            a.click();
                            window.URL.revokeObjectURL(url);
                            a.remove();
                        }

                        BackupRestorePage.showspinner = false;
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
                        BackupRestorePage.showspinner = true;
                        m.redraw();
                        uploaderFunc(readerEvent.target.result)
                            .catch(function(e) {
                                BackupRestorePage.showspinner = false;
                                throw e;
                            })
                            .then(function() {
                                BackupRestorePage.showspinner = false;
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

        var makeBackupTokenButtonForm = function(saveFunc, disableFunc, isTokenEnabled, tokenPostedObj, tokenPostedObjSetter, exampleTarFileName) {
            return m("form.pure-form.pure-form-aligned",
                m("fieldset", [
                    m(".pure-controls", [
                        m("button.pure-button pure-button-primary", {
                            onclick: function(e) {
                                e.preventDefault();

                                if (isTokenEnabled && !confirm("Generating a new token will invalidate your old external backup token. Are you sure?")) {
                                    return;
                                }

                                saveFunc().then(function(result) {
                                    tokenPostedObjSetter(result);
                                    BackupRestore.loadBackupTokenStatus();
                                });
                            }
                        }, !isTokenEnabled ? "Enable external tar backup" : "Generate new token for external tar backup")
                    ])
                ]),
                isTokenEnabled ? m("fieldset", [
                    m(".pure-controls", [
                        m("button[type=submit].pure-button pure-button-primary", {
                            onclick: function(e) {
                                e.preventDefault();

                                if (!confirm("This will disable external tar backup. Are you sure?")) {
                                    return;
                                }

                                disableFunc().then(function(result) {
                                    BackupRestore.loadBackupTokenStatus();
                                });
                            }
                        },
                        "Disable external tar backup")
                    ])
                ]) : null,
                (isTokenEnabled && tokenPostedObj.token) ? [
                    m("p", "Backup tar URL:"),
                    m("pre.code", tokenPostedObj.url),
                    m("p", "Bearer token:"),
                    m("pre.code", tokenPostedObj.token),
                    m("p", "Wget example line:"),
                    m("pre.code", "wget " + tokenPostedObj.url + " \\\n-O " + exampleTarFileName + " \\\n--header \"Authorization: Bearer " + tokenPostedObj.token + "\"")
                ] : null
            );
        }

        return [
            BackupRestorePage.showspinner ? m(".spinneroverlay", m(".spinner")) : null,

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

            m("h3.content-subhead", "Tar file with encrypted secrets"),

            m("p", m("a[href=]", {
                onclick: makeFileDownloader(BackupRestore.getTarSecrets, "secrets.tar", "application/tar")
            }, "Export all encrypted secrets in a tar file")),

            m("p", [
                m("a[href=]", {
                    onclick: makeFileUploader(BackupRestore.putTarSecrets, "Successfully imported and replaced secrets from tar of encrypted secrets.")
                }, "Import and replace secrets from tar of encrypted secrets")
            ]),

            m("h3.content-subhead", "Tar file of users, keys, and groups"),

            m("p", m("a[href=]", {
                onclick: makeFileDownloader(BackupRestore.getTarUsers, "users.tar", "application/tar")
            }, "Export all users and their encryption keys and groups to a tar")),

            m("p", [
                m("a[href=]", {
                    onclick: makeFileUploader(BackupRestore.putTarUsers, "Successfully imported users and their encryption keys and groups from given tar.")
                }, "Import users and their encryption keys and groups from a tar")
            ]),

            m("h3.content-subhead", "External backup of secrets"),

            makeBackupTokenButtonForm(
                BackupRestore.secretsBackupTokenSave,
                BackupRestore.secretsBackupTokenDisable,
                BackupRestore.backupTokenStatus.secretBackupTokenEnabled,
                BackupRestore.secretsBackupToken,
                function(value) { BackupRestore.secretsBackupToken = value; },
                "secrets.tar"
            ),

            m("h3.content-subhead", "External backup of users, keys, and groups"),

            makeBackupTokenButtonForm(
                BackupRestore.usersBackupTokenSave,
                BackupRestore.usersBackupTokenDisable,
                BackupRestore.backupTokenStatus.usersBackupTokenEnabled,
                BackupRestore.usersBackupToken,
                function(value) { BackupRestore.usersBackupToken = value; },
                "users.tar"
            ),
        ];
    }
}
