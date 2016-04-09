var UserEdit = {

  controller: function() {
    var self = this;

    // Variables
    this.user = m.prop( new User({id: Session.username() }));
    console.debug(this.user());

    this.statusMessage = m.prop("");


    // Functions
    this.save = function() {
      m.startComputation();

      self.user().save().then(function(data) {
        self.statusMessage("Updated")
        m.endComputation();

      }, function(a) {      
        m.route("/logout");
        m.endComputation();

      });

      return false;
    };

  },

  view: function(ctrl) {
    var inputField = function(text, field, type) { 
      return m(".pure-control-group", [
        m("label", text),
        m("input", { type: type || "text", oninput: m.withAttr("value", field), value: field() } )
      ]);
    };

    return m("#applayout", [
      Templates.navigation(),
      m("#content", [
        m(".user-edit", [

          m("h2", "Change password"),

          ctrl.statusMessage() ? m('p', ctrl.statusMessage()) : '',

          m("form.pure-form.pure-form-aligned", 
            m("fieldset", [
              inputField("Password", ctrl.user().password, "password"),

              m(".pure-controls", [
                m('button.pure-button.pure-button-primary', { onclick: ctrl.save }, 'Save'),
              ]),
            ])
          )
        ])
      ])
    ])

  }

};