var Secrets = {

  controller: function() {
    var self = this;

    this.secrets = m.prop();

    m.startComputation();

    m.request({
      method: "GET",
      url: "http://46.101.38.96/api/secret",
      config: xhrConfig
    }).then(function(data) {
      self.secrets(data);
      m.endComputation();

    }, function(a) {
      m.route('/login');
    });
  },

  view: function(ctrl) {
    return [
      Templates.navigation(),
      m('.secrets', [
        m('h1', 'Secrets'),

        ctrl.secrets().map(function(secret) {
          return secret.id;
        })

      ])
    ]
  }

};