var AdminMenu = {

  controller: function() {
    var self = this;
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
        m("h2", "Administration"),
        m("p", m("a", { onclick: function() { m.route("/user") } }, "Create user")),
        m("p", m("a", { onclick: function() { m.route("/change-my-password") } }, "Change my password")),
      ])
    ])

  }
};
