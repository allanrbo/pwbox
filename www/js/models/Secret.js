var Secret = ModelFramework(function(content) {
	content = content || {};

  // Fields
  this.id = m.prop(content.id || "");
  this.title = m.prop(content.title || "");
  this.username = m.prop(content.username || "");
  this.password = m.prop(content.password || "");
  this.notes = m.prop(content.notes || "");
  this.recipients = m.prop(content.recipients || []);

  this.saveFields = ['title', 'username', 'password', 'notes', 'recipients'];

}, config.api + 'secret');
