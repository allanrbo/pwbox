var ModelFramework = function(model, endpoint) {

	model.endpoint = endpoint;


  // Methods
  model.prototype.save = function() {
    var self = this;
    console.log(model());

    return m.request({
      method: self.id() ? "PUT" : "POST",
      url: endpoint + (self.id() ? "/" + self.id() : ""),
      data: this.dataSave(),
      config: xhrConfig
    });
  };

  model.prototype.dataSave = function() {
    var self = this;

    var output = {}; 
    this.saveFields.forEach(function(key) {
      output[key] = self[key];
    });
    return output;
  }

  model.prototype.delete = function() {
    if(!this.id()) {
      console.error("Cannot delete Secret that is not saved.");
      return;
    }

    return m.request({
      method: "DELETE",
      url: endpoint + "/" + this.id(),
      config: xhrConfig
    });
  }

  // Static methods
  model.get = function(id) {
    return m.request({
      method: "GET",
      url: endpoint + "/" + id,
      config: xhrConfig,
      type: model
    }).then(function() {}, function() {
      
      // Not logged in
      Session.logout();
      m.route('/login');



    });
  };
	

  model.all = function() {
    var deferred = m.deferred();

    m.request({
      method: "GET",
      url: endpoint,
      config: xhrConfig,
      background: true

    }).then(function(data) {

      var records = data.map(function(secret) {
        return new model(secret);
      });

      deferred.resolve(records);

    }, function(b,c) {

      if(b.message.indexOf('auth') > -1) {
        // Not logged in
        Session.logout();
        m.route('/login');
        m.endComputation();

      }

      deferred.reject();
    });

    return deferred.promise;
  };

	
	return model;
};