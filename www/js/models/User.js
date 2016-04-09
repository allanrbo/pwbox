var User = function(content) {
	content = content || {};

  // Fields
  this.username = m.prop(content.username || "");
};

User.all = function() {
  var deferred = m.deferred();

  m.request({
    method: "GET",
    url: config.api + "user",
    config: xhrConfig,
    background: true

  }).then(function(data) {

    var records = data.map(function(user) {
      var record = new User({username: user});
      return record;
    });

    deferred.resolve(records);

  }, function() {
    deferred.reject();
  });

  return deferred.promise;
};


