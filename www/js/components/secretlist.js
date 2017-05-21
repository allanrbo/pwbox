var SecretList = {

    controller: function() {
        var self = this;

        // Variables
        this.secrets = m.prop([]);
        this.deleteMessage = m.prop('');

        // Functions
        this.editSecret = function(id) {
            m.route("/secrets/" + id);
        };

        this.loadSecrets = function() {
            m.startComputation();
            Secret.all().then(function(secrets) {
                self.secrets(secrets);
                m.endComputation();
            });
        };

        // Initialize
        self.loadSecrets();
    },

    view: function(ctrl) {

        return m("#applayout", [
            Templates.navigation(),
            m("#content.secrets", [
                m("h2", "Secrets"),

                ctrl.deleteMessage() ? m("p", ctrl.deleteMessage()) : "",

                ctrl.secrets().length > 0 ? [
                    m('table.pure-table.pure-table-horizontal', [
                        m("thead",
                            m('tr', [
                                m('th', 'Name'),
                                m('th', 'Modified')
                            ])
                        ),

                        ctrl.secrets().map(function(secret) {
                            return m('tr', [
                                m('td', m('a', {
                                    "data-id": secret.id(),
                                    onclick: m.withAttr("data-id", ctrl.editSecret)
                                }, secret.title())),
                                m('td', secret.modified()),
                            ])
                        })
                    ])
                ] : m("p", "No secrets in database")
            ])
        ])
    }

};