var Session = function() {

  var token = function(t) {
    if(typeof t !== 'undefined') {
      localStorage.setItem("token", t);
    }
    return localStorage.getItem("token");
  };

  var username = function(t) {
    if(typeof t !== 'undefined') {
      localStorage.setItem("username", t);
    }
    return localStorage.getItem("username");
  };

  var login = function(user, password) {
      return m.request({
        method: "POST",
        url: config.api + "authenticate",
        data: {
          username: user,
          password: password
        }
      }).then(function(data) {
        username(user);
        token(data.token);
      }, function(a) {
        logout();
      });
  };

  var logout = function() {
    token("");
    username("");
  };

  return {
    token: token,
    username: username,
    login: login,
    logout: logout
  }

}();
