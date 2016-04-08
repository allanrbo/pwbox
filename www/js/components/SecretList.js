var SecretList = {

  controller: function() {
    var self = this;

    // Variables
    this.secrets = m.prop([]);

    // Functions
    this.editSecret = function(id) {
      m.route("/secrets/" + id);
    };

    // Initialize
    m.startComputation();

    m.request({
      method: "GET",
      url: config.api + "secret",
      config: xhrConfig
    }).then(function(data) {
      self.secrets(data || []);
      m.endComputation();

    }, function(a) {
      m.route("/login");
      m.endComputation();
    });
  },

  view: function(ctrl) {
    console.debug('Secrets view');

    return m("#applayout", [
      Templates.navigation(),
      m("#content.secrets", [
        m("h2", "Secrets"),

        ctrl.secrets().length > 0 ? [

          m('table.pure-table.pure-table-horizontal', [
            m("thead",
              m('tr', [
                m('th', 'Title')
                ]
              )
            ),

            ctrl.secrets().map(function(secret) {
              return m('tr', [
                m('td', m('a', { "data-id": secret.id, onclick: m.withAttr("data-id", ctrl.editSecret) }, secret.title)),
                ])
            })

          ])

        ] : m("p", "No secrets in database")


      ])
    ])
  }

};