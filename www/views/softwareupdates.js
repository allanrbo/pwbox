var SoftwareUpdatesPage = {
    oninit: function(vnode) {
        SoftwareUpdates.hostInfo = {};
        SoftwareUpdates.updateOsCheckResult = {};
        SoftwareUpdates.updateOsPerformResult = {};
        SoftwareUpdates.updateOsPerformStatusResult = {};

        SoftwareUpdates.getHostInfo();

        SoftwareUpdatesPage.showspinner = false;
    },

    view: function() {
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
                    SoftwareUpdates.updateOsPerformStatusResult = {};
                    SoftwareUpdatesPage.showspinner = true;
                    SoftwareUpdates.updateOsCheck().then(function() {
                        SoftwareUpdatesPage.showspinner = false;
                    });
                }
            }, "Check for operating system updates")),

            SoftwareUpdates.updateOsCheckResult.upgradable && SoftwareUpdates.updateOsPerformStatusResult.status != "complete" ? [
                m("form.pure-form.pure-form-aligned",
                    m("fieldset", [
                        m(".pure-controls", [
                            m("button.pure-button pure-button-primary", {
                                onclick: function(e) {
                                    e.preventDefault();
                                    SoftwareUpdatesPage.showspinner = true;
                                    SoftwareUpdates.updateOsPerform().then(function() {
                                        var ref = {};
                                        var f = function() {
                                            SoftwareUpdates.getUpdateOsPerformStatus(SoftwareUpdates.updateOsPerformResult.updateJobId).then(function() {
                                                if (SoftwareUpdates.updateOsPerformStatusResult.status == "complete") {
                                                    SoftwareUpdatesPage.showspinner = false;
                                                    clearInterval(ref.r);
                                                }
                                            });
                                        };

                                        ref.r = setInterval(f, 2000);
                                    });
                                }
                            }, "Perform operating system updates")
                        ])
                    ])
                )
            ] : null,

            !SoftwareUpdates.updateOsCheckResult.consoleoutput || SoftwareUpdates.updateOsPerformStatusResult.consoleoutput ? null : [
                m("p", "OS update check results:"),
                m("pre.code", SoftwareUpdates.updateOsCheckResult.consoleoutput)
            ],

            !SoftwareUpdates.updateOsPerformStatusResult.consoleoutput ? null : [
                m("p", "OS update results:"),
                m("pre.code", SoftwareUpdates.updateOsPerformStatusResult.consoleoutput)
            ]
        ];
    }
}
