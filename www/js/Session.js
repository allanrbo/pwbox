var Session = function() {

  var token = function(t) {
    if(typeof t !== 'undefined') {
      localStorage.setItem("token", t);
    }
    return localStorage.getItem("token")
  };

  var login = function(username, password) {
      return m.request({
        method: "POST",
        url: "http://46.101.38.96/api/authenticate",
        data: {
          username: username,
          password: password
        }

      }).then(function(data) {
        localStorage.setItem("token", data.token);

      }, function(a) {
        self.errorMessage(a.message);

      });
  };

  var logout = function() {
    token("");
  };

  return {
    token: token,
    login: login,
    logout: logout
  }

}();

