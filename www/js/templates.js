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
        menuItem("New secret", "/secrets/new"),
        menuItem("Change my password", "/user"),
        m("li.pure-menu-item.menu-item-divided", m("a.pure-menu-link", { onclick: function() { m.route("/logout") } }, "Log out"))
      ])
    ])
  )

};

Templates.usersSelector = function(users, selectedUsernames) {

  var setSelectedUsers = function() {
    var current = selectedUsernames();
    var index = current.indexOf(this.value);

    if(index == -1 && this.checked) {
      current.push(this.value);
    }
    else if(index > -1 && !this.checked) {
      current.splice(index, 1);
    }

    selectedUsernames(current);
  };


  return m(".users-selector", 
    m("ul", [
      users().map(function(user) {

        return m("li",
          m("label", [
            m("input", { type: "checkbox", checked: selectedUsernames().indexOf(user.username()) > -1, value: user.username(), onclick: setSelectedUsers } ),
            " " + user.username()
          ])
        ) 
      })
    ])
  );

}