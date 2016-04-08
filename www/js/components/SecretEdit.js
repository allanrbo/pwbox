var SecretEdit = {

  controller: function() {
    var self = this;

    // Variables
    this.id = m.prop(m.route.param("id"));
    this.title = m.prop("");
    this.username = m.prop("");
    this.password = m.prop("");
    this.notes = m.prop("");
    this.additionalUsers = m.prop("");

    this.statusMessage = m.prop("");
    this.exists = self.id() && self.id() != "new";


    // Functions
    this.save = function() {

      m.startComputation();

      var data = {
        title: self.title(),
        username: self.username(),
        password: self.password(),
        notes: self.notes(),
        additionalUsers: self.additionalUsers(),
      };

      m.request({
        method: self.exists ? "PUT" : "POST",
        url: config.api + "secret" + (self.exists ? "/" + self.id() : ""),
        data: data,
        config: xhrConfig
      }).then(function(data) {

        self.statusMessage("Updated")
        m.endComputation();

      }, function(a) {      

        m.route("/logout");
        m.endComputation();

      });

    };


    // Initialize controller
    if(this.exists) {
      m.startComputation();

      m.request({
        method: "GET",
        url: config.api + "secret/" + self.id(),
        config: xhrConfig
      }).then(function(data) {

        self.title(data.title || '');
        self.username(data.username || '');
        self.password(data.password || '');
        self.notes(data.notes || '');
        self.additionalUsers(data.additionalUsers || '');
        console.log('load');
        m.endComputation();

      }, function(a) {

        m.route("/login");
        m.endComputation();

      });
    }

  },

  view: function(ctrl) {

    var inputField = function(text, field) { 
      return m(".pure-control-group", [
        m("label", text),
        m("input", { oninput: m.withAttr("value", field), value: field() } )
      ]);
    };

    return m("#applayout", [
      Templates.navigation(),
      m("#content", [
        m(".secret-edit", [
          m("h2", "Edit secret"),

          ctrl.statusMessage() ? m('p', ctrl.statusMessage()) : '',

          m("form.pure-form.pure-form-aligned", 
            m("fieldset", [
              inputField("Title", ctrl.title),
              inputField("Username", ctrl.username),
              inputField("Password", ctrl.password),
              inputField("Notes", ctrl.notes),
              inputField("Additional users", ctrl.additionalUsers),
              m(".pure-controls", [
                m('button.pure-button.pure-button-primary', { onclick: ctrl.save }, 'Save'),
                m("p", m("a", { onclick: function() { m.route('/secrets') } }, "Back to list"))
              ]),
            ])
          )
        ])
      ])
    ])
  }

};