var SecretList = {

  controller: function() {
    var self = this;

    // Variables
    this.secrets = m.prop([]);

    // Functions
    this.editSecret = function(id) {
      m.route("/secrets/" + id);
    };

    this.addSecret = function(id) {
      m.route("/secrets/new");
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

    return [
      Templates.navigation(),
      m(".secrets", [
        m("h1", "Secrets"),

        m("a", { onclick: ctrl.addSecret }, "Add new secret"),

        ctrl.secrets().length > 0 ? [

          m('table', [

            m('tr', [
              m('th', 'Title')
              ]
            ),

            ctrl.secrets().map(function(secret) {
              return m('tr', [
                m('td', m('a', { "data-id": secret.id, onclick: m.withAttr("data-id", ctrl.editSecret) }, secret.title)),
                ])
            })

          ])

        ] : m("p", "No secrets in database")


      ])
    ]
  }

};