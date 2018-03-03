var BackupRestore = {
    oninit: function(vnode) {
    },

    view: function() {
        return [
            m("h2.content-subhead", "Backup and restore"),
            m("p", m("a[href=/admin]", {oncreate: m.route.link}, "Export all secrets in plain text to CSV")),
            m("p", m("a[href=/admin]", {oncreate: m.route.link}, "Import from CSV")),
            m("p", m("a[href=/admin]", {oncreate: m.route.link}, "Export all secrets encrypted in a tar.gz")),
            m("p", m("a[href=/admin]", {oncreate: m.route.link}, "Import encrypted secrets from a tar.gz")),
            m("p", m("a[href=/admin]", {oncreate: m.route.link}, "Export all users and their encryption keys to a tar.gz")),
            m("p", m("a[href=/admin]", {oncreate: m.route.link}, "Import users and their encryption keys from a tar.gz")),
        ];
    }
}
