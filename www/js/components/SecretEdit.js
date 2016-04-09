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
        url: "http://46.101.38.96/api/secret" + (self.exists ? "/" + self.id() : ""),
        data: data,
        config: xhrConfig
      }).then(function(data) {

        self.statusMessage("Updated")
        m.endComputation();

      }, function(a) {      

        m.route("/login");
        m.endComputation();

      });

    };


    // Initialize controller
    if(this.exists) {
      m.startComputation();

      m.request({
        method: "GET",
        url: "http://46.101.38.96/api/secret/" + self.id(),
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
      return m("div.field-wrapper", m("label", [
        text,
        m("input", { oninput: m.withAttr("value", field), value: field() } )
      ]))
    };

    return [
      Templates.navigation(),
      m(".secret-edit", [
        m("h1", "Edit secret"),

        ctrl.statusMessage() ? m('p', ctrl.statusMessage()) : '',

        m(".form", [

          inputField("Title", ctrl.title),
          inputField("Username", ctrl.username),
          inputField("Password", ctrl.password),
          inputField("Notes", ctrl.notes),
          inputField("Additional users", ctrl.additionalUsers),

          m('button', { onclick: ctrl.save }, 'Save'),

          m("p", m("a", { onclick: function() { m.route('/secrets') } }, "Back to list")),


        ])

      ])
    ]
  }

};