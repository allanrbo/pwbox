var User = ModelFramework(function(content) {
    content = content || {};

    // Fields
    this.id = m.prop(content.id || "");
    this.username = m.prop(content.username || "");
    this.password = m.prop(content.password || "");

    this.saveFields = ['username', 'password'];

}, config.api + 'user');