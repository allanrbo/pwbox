var xhrConfig = function(xhr) {
  xhr.setRequestHeader("Content-Type", "application/json");
  xhr.setRequestHeader("Authorization", "Bearer " + Session.token());
};

m.route.mode = "hash";

m.route(document.body, "/secrets", {
  "/login": Login,
  "/secrets": SecretList,
  "/secrets/:id": SecretEdit,
  "/secrets/new": SecretEdit,
  "/user": UserEdit,
  "/logout": {
    controller: function() {
      Session.logout();
      m.route("login");
    }
  }
});
