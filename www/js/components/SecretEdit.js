var SecretEdit = {

  controller: function() {
    var self = this;

    // Variables
    this.secret = m.prop(new Secret());

    this.allUsers = m.prop();
    this.statusMessage = m.prop("");


    // Functions
    this.save = function() {
      m.startComputation();

      self.secret().save().then(function(data) {
        self.statusMessage("Updated")
        m.endComputation();

      }, function(a) {      
        m.route("/logout");
        m.endComputation();

      });

      return false;
    };

    // Initialize controller
    m.startComputation();
    var promises = [];

    // Setup all users
    var usersPromise = User.all().then(this.allUsers);
    promises.push(usersPromise);

    // Setup secret
    if(m.route.param('id')) {
      var secretPromise = Secret.get( m.route.param('id') ).then(this.secret);
      promises.push(secretPromise);
    }

    m.sync(promises).then(function(args) {
      m.endComputation();
    });

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
          m("h2", (ctrl.exists ? "Edit" : "New") + " secret"),

          ctrl.statusMessage() ? m('p', ctrl.statusMessage()) : '',

          m("form.pure-form.pure-form-aligned", 
            m("fieldset", [
              inputField("Title", ctrl.secret().title),
              inputField("Username", ctrl.secret().username),
              inputField("Password", ctrl.secret().password),
              inputField("Notes", ctrl.secret().notes),

              Templates.usersSelector(ctrl.allUsers, ctrl.secret().recipients),

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