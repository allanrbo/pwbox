var SecretForm = {
    modified: false,

    oninit: function(vnode) {
        SecretForm.modified = false;

        Secret.current = {};
        if (vnode.attrs.key != "new") {
            Secret.load(vnode.attrs.key);
        }

        Group.loadList();
        User.loadList();
    },

    onremove: function(vnode) {
        Secret.current = {};
    },

    view: function(vnode) {
        var loading = false;
        if (vnode.attrs.key != "new") {
            if (!Secret.currentLoaded || !Group.listLoaded || !User.listLoaded) {
                loading = true;
            }
        } else {
            if (!Group.listLoaded || !User.listLoaded) {
                loading = true;
            }
        }

        var deleteButton = null;
        if (Secret.current.id) {
            deleteButton = m("button.pure-button", {onclick: function(e) {
                e.preventDefault();
                if (confirm("Really delete secret \"" + Secret.current.title + "\"?")) {
                    Secret.delete().then(function() {
                        m.route.set("/secrets");
                    });
                }
            }}, "Delete");
        }

        var availableGroups = [];
        for (var i = 0; i < Group.list.length; i++) {
            if (Group.list[i].name != "Administrators") {
                availableGroups.push(Group.list[i]);
            }
        }

        var shareWithUserGroups = null;
        if (availableGroups.length > 0) {
            shareWithUserGroups = m(".pure-control-group.custom-control-group", [
                m("label", "Share with groups"),
                m(".custom-control",
                    availableGroups.map(function(group) {
                        var checked = false;
                        if (Secret.current.groups && Secret.current.groups.indexOf(group.name) > -1) {
                            checked = true;
                        }

                        return m("div",
                            m("label", [
                                m("input", {
                                    type: "checkbox",
                                    checked: checked,
                                    value: group.name,
                                    onclick: function() {
                                        Secret.toggleGroup(group.name);
                                        SecretForm.modified = true;
                                    }
                                }),
                                " " + group.name
                            ])
                        );
                    })
                )
            ]);
        }

        var attachmentsRows = null;
        if (Secret.current.attachments) {
            // Copied from https://chawi3.wordpress.com/2015/03/03/arraybuffer-to-base64-base64-to-arraybuffer-javascript/
            function base64ToArrayBuffer(base64) {
                var binary_string =  window.atob(base64);
                var len = binary_string.length;
                var bytes = new Uint8Array(len);
                for (var i = 0; i < len; i++) {
                    bytes[i] = binary_string.charCodeAt(i);
                }

                return bytes.buffer;
            }

            attachmentsRows = [];
            for (var i = 0; i < Secret.current.attachments.length; i++) {
                attachment = Secret.current.attachments[i];

                var mimeType = "application/octet-binary";
                var viewable = false;
                var s = attachment.name.toLowerCase();

                if (s.endsWith(".jpg") || s.endsWith(".jpeg")) {
                    mimeType = "image/jpeg";
                    viewable = true;
                }

                if (s.endsWith(".png")) {
                    mimeType = "image/png";
                    viewable = true;
                }

                if (s.endsWith(".gif")) {
                    mimeType = "image/gif";
                    viewable = true;
                }

                if (s.endsWith(".txt")) {
                    mimeType = "text/plain";
                    viewable = true;
                }

                if (s.endsWith(".html") || s.endsWith(".htm")) {
                    mimeType = "text/html";
                    viewable = true;
                }

                var blob = new Blob([base64ToArrayBuffer(attachment.data)], {type: mimeType});

                var objUrl = window.URL.createObjectURL(blob);

                var attachmentRow = m("div", [
                    attachment.name,
                    " [ ",
                    !viewable ? null : m("a", {href: objUrl }, m("span.fa.fa-eye")),
                    " ",
                    m("a", {download: attachment.name, href: objUrl }, m("span.fa.fa-download")),
                    " ",
                    m("a[href=]", {
                        onclick: function(i, attachmentName) {
                            return function() {
                                if (confirm("Are you sure you want to remove " + attachmentName + "?")) {
                                    Secret.current.attachments.splice(i, 1);
                                    SecretForm.modified = true;
                                }
                                return false; }
                            }(i, attachment.name)
                        }, m("span.fa.fa-trash-o")),
                    " ] "
                ]);

                attachmentsRows.push(attachmentRow);
            }
        }

        var makeFileAttacher = function() {
            return function() {
                var input = document.createElement("input");
                input.type = "file";
                input.style = "display: none";
                input.onchange = function(inputEvent) {
                    var filename = inputEvent.target.files[0].name;
                    var reader = new FileReader();
                    reader.onload = function (readerEvent) {
                        // Copied from https://chawi3.wordpress.com/2015/03/03/arraybuffer-to-base64-base64-to-arraybuffer-javascript/
                        function arrayBufferToBase64(buffer) {
                            var binary = '';
                            var bytes = new Uint8Array(buffer);
                            var len = bytes.byteLength;
                            for (var i = 0; i < len; i++) {
                                binary += String.fromCharCode(bytes[i]);
                            }

                            return window.btoa(binary);
                        }

                        if (!Secret.current.attachments) {
                            Secret.current.attachments = [];
                        }

                        Secret.current.attachments.push({
                            name: filename,
                            data: arrayBufferToBase64(readerEvent.target.result)
                        });

                        SecretForm.modified = true;

                        m.redraw();
                    }

                    reader.readAsArrayBuffer(inputEvent.target.files[0]);
                };

                document.body.appendChild(input);
                input.click();
                input.remove();

                return false;
            }
        };

        var owner = null;
        if (User.list.length > 1) {
            owner = m(".pure-control-group", [
                m("label[for=Owner]", "Owner"),
                m("select#owner", {
                    onchange: m.withAttr("value", function(value) {
                        Secret.current.owner = value;
                        SecretForm.modified = true;
                    })},
                    User.list.map(function(user) {
                        var selected = "";
                        if ((!Secret.current.owner && user.username == Session.getUsername()) || (Secret.current.owner == user.username)) {
                            selected = "selected";
                        }

                        return m("option", {
                            value: user.username,
                            selected: selected
                        }, user.username);
                    })
                )
            ]);
        }

        var lastModified = null;
        if (Secret.current.modified) {

            var formatDate = function(date) {
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
                    + ":" + pad(date.getMinutes())
                    + ":" + pad(date.getSeconds());
            };

            lastModified = m(".pure-control-group.custom-control-group", [
                m("label", "Last modified"),
                m(".custom-control", formatDate(Secret.current.modified))
            ]);
        }

        var form = m("form.pure-form.pure-form-aligned.secretsForm", {
                onsubmit: function(e) {
                    e.preventDefault();

                    if (SecretForm.modified) {
                        Secret.save().then(function() {
                            m.route.set("/secrets");
                        });
                    } else {
                        m.route.set("/secrets");
                    }
                }
            },
            m("fieldset", [
                m(".pure-control-group", [
                    m("label[for=title]", "Title"),
                    m("input#title[type=text][autocomplete=off]", {
                        oncreate: function(vnode) {
                            setTimeout(function() {
                                vnode.dom.focus();
                            }, 0);
                        },
                        oninput: m.withAttr("value", function(value) {
                            Secret.current.title = value;
                            SecretForm.modified = true;
                        }),
                        value: Secret.current.title
                    }),
                ]),

                m(".pure-control-group", [
                    m("label[for=username]", "Username"),
                    m("input#username[type=text][autocomplete=off]", {
                        oninput: m.withAttr("value", function(value) {
                            Secret.current.username = value;
                            SecretForm.modified = true;
                        }),
                        value: Secret.current.username
                    }),
                ]),

                m(".pure-control-group", [
                    m("label[for=password]", "Password"),
                    m("input#password[type=text][autocomplete=off]", {
                        oninput: m.withAttr("value", function(value) {
                            Secret.current.password = value;
                            SecretForm.modified = true;
                        }),
                        value: Secret.current.password
                    }),
                    " ",
                    m("a[href=]", {
                        onclick: function(e) {
                            e.preventDefault();

                            var text = "";
                            // Excluding chars that can look ambigious, such as o and 0
                            var possible = "abcdefghjkmnpqrstuvwxyz";
                            for (var i = 0; i < 20; i++) {
                                text += possible.charAt(Math.floor(Math.random() * possible.length));
                            }

                            var upper1 = 0, upper2 = 0, lower1 = 0, lower2 = 0, special1 = 0, special2 = 0, number1 = 0, number2 = 0;
                            var noCollide = [0, text.length - 1];
                            while(noCollide.indexOf(upper1) != -1) upper1 = Math.floor(Math.random() * (text.length - 1));
                            noCollide.push(upper1);
                            while(noCollide.indexOf(upper2) != -1) upper2 = Math.floor(Math.random() * (text.length - 1));
                            noCollide.push(upper2);
                            while(noCollide.indexOf(special1) != -1) special1 = Math.floor(Math.random() * (text.length - 1));
                            noCollide.push(special1);
                            while(noCollide.indexOf(special2) != -1) special2 = Math.floor(Math.random() * (text.length - 1));
                            noCollide.push(special2);
                            while(noCollide.indexOf(number1) != -1) number1 = Math.floor(Math.random() * (text.length - 1));
                            noCollide.push(number1);
                            while(noCollide.indexOf(number2) != -1) number2 = Math.floor(Math.random() * (text.length - 1));
                            noCollide.push(number2);

                            text = text.substring(0, upper1) + text.charAt(upper1).toUpperCase() + text.substring(upper1 + 1);
                            text = text.substring(0, upper2) + text.charAt(upper2).toUpperCase() + text.substring(upper2 + 1);

                            var possibleSpecial = ",.!#%";
                            text = text.substring(0, special1) + possibleSpecial.charAt(Math.floor(Math.random() * possibleSpecial.length)) + text.substring(special1 + 1);
                            text = text.substring(0, special2) + possibleSpecial.charAt(Math.floor(Math.random() * possibleSpecial.length)) + text.substring(special2 + 1);

                            var possibleNumbers = "23456789";
                            text = text.substring(0, number1) + possibleNumbers.charAt(Math.floor(Math.random() * possibleNumbers.length)) + text.substring(number1 + 1);
                            text = text.substring(0, number2) + possibleNumbers.charAt(Math.floor(Math.random() * possibleNumbers.length)) + text.substring(number2 + 1);

                            Secret.current.password = text;
                            SecretForm.modified = true;
                        }
                    }, m("span.fa.fa-random", {style: "vertical-align: middle;"})),
                ]),

                m(".pure-control-group", [
                    m("label[for=notes]", "Notes"),
                    m("textarea#notes", {
                        oninput: m.withAttr("value", function(value) {
                            Secret.current.notes = value;
                            SecretForm.modified = true;
                        }),
                        value: Secret.current.notes
                    }),
                ]),

                m(".pure-control-group.custom-control-group", [
                    m("label", "File attachments"),

                    m(".custom-control", [
                        m("div",  [
                            attachmentsRows,
                            m("a[href=]", {
                                onclick: makeFileAttacher()
                            }, "Attach file"),
                        ])
                    ])
                ]),

                owner,

                shareWithUserGroups,

                lastModified,

                m(".pure-controls", [
                    m("button[type=submit].pure-button pure-button-primary", SecretForm.modified ? "Save" : "Back"),
                    SecretForm.modified ? [
                        " ",
                        m("button.pure-button", {onclick: function(e) {
                            e.preventDefault();
                            m.route.set("/secrets");
                        }}, "Back")
                    ] : null,
                    " ",
                    deleteButton
                ]),
            ])
        );

        return [
            m("h2.content-subhead", "Secret"),
            loading ? "Loading..." : form
        ];
    }
}
