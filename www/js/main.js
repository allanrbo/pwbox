var xhrConfig = function(xhr) {
  xhr.setRequestHeader("Content-Type", "application/json");
  xhr.setRequestHeader("Authorization", "Bearer " + localStorage.getItem("token"));
};

m.route.mode = "hash";


m.route(document.body, "/secrets", {
  "/login": Login,
  "/secrets": Secrets
});
