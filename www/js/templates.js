var Templates = {};

Templates.navigation = function() {
	
  var menuItem = function(text, route) {
    return m("li", m("a", { onclick: function() { m.route(route) } }, text));
  };

  return m(".navigation",
	  m("ul", [
      menuItem("List secrets", "/secrets"),
      menuItem("New secret", "/secrets/new")
	  ])
	)

};