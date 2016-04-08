var Templates = {};

Templates.navigation = function() {
  
  var menuItem = function(text, route) {
    return m("li.pure-menu-item", m("a.pure-menu-link", { onclick: function() { m.route(route) } }, text));
  };

  return m("#menu",
    m(".pure-menu", [
      m("a.pure-menu-heading", { href: "#" }, "PwBox"),
      m("ul.pure-menu-list", [
        menuItem("List secrets", "/secrets"),
        menuItem("New secret", "/secrets/new")
      ])
    ])
  )

};
