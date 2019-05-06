var SoftwareUpdatesPage = {
    oninit: function(vnode) {
        SoftwareUpdates.hostInfo = {};
        SoftwareUpdates.updateOsCheckResult = {};
        SoftwareUpdates.updateOsPerformResult = {};

        SoftwareUpdates.getHostInfo();
        SoftwareUpdates.loadUpdateOsLogsList();

        SoftwareUpdatesPage.showspinner = false;
    },

    view: function() {

        var updateOsLogsTable = SoftwareUpdates.updateOsLogsListLoaded ? "No operating system update logs yet." : "Loading operating system update logs...";

        if (SoftwareUpdates.updateOsLogsList.length > 0) {
            updateOsLogsTable = [
                m("table.pure-table.pure-table-horizontal.secretslist", [
                    m("thead", [
                        m("tr", [
                            m("th.title", "Log file name"),
                        ])
                    ]),
                    m("tbody", SoftwareUpdates.updateOsLogsList.map(function(row) {
                        return m("tr", [
                            m("td", m("a", {href: "/admin/softwareupdates/updateoslogs/" + row, oncreate: m.route.link}, row))
                        ]);
                    }))
                ])
            ];
        }

        return [
            SoftwareUpdatesPage.showspinner ? m(".spinneroverlay", m(".spinner")) : null,

            m("h2.content-subhead", "Software updates"),

            m("h3.content-subhead", "Host info"),
            m("pre.code", SoftwareUpdates.hostInfo.consoleoutput),

            m("h3.content-subhead", "Operating system updates"),

            m("p", m("a[href=]", {
                onclick: function(e) {
                    e.preventDefault();
                    SoftwareUpdates.updateOsCheckResult = {};
                    SoftwareUpdates.updateOsPerformResult = {};
                    SoftwareUpdatesPage.showspinner = true;
                    SoftwareUpdates.updateOsCheck().then(function() {
                        SoftwareUpdatesPage.showspinner = false;
                    });
                }
            }, "Check for operating system updates")),

            // SoftwareUpdates.updateOsCheckResult.upgradable && SoftwareUpdates.updateOsPerformStatusResult.status != "complete" ? [
            true ? [
                m("form.pure-form.pure-form-aligned",
                    m("fieldset", [
                        m(".pure-controls", [
                            m("button.pure-button pure-button-primary", {
                                onclick: function(e) {
                                    e.preventDefault();
                                    SoftwareUpdates.updateOsPerform().then(function() {
                                        alert("OS updated started. Check status in log file to ensure successful completion: " + SoftwareUpdates.updateOsPerformResult.updateJobId);
                                        SoftwareUpdates.loadUpdateOsLogsList();
                                    });
                                }
                            }, "Perform operating system updates")
                        ])
                    ])
                )
            ] : null,

            m("h3.content-subhead", "Operating system update logs"),
            updateOsLogsTable,


            // m("h3.content-subhead", "PwBox updates"),

            // m("p", m("a[href=]", {
            //     onclick: function(e) {
            //         e.preventDefault();
            //     }
            // }, "Check for PwBox updates")),

        ];
    }
}
