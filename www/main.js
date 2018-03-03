var xhrConfig = function(xhr) {
  xhr.setRequestHeader("Content-Type", "application/json");
  xhr.setRequestHeader("Authorization", "Bearer " + Session.getToken());
};

var handleUnauthorized = function(e) {
    if(e.status == "unauthorized") {
        Session.logout();
        m.route.set('/login');
    } else {
        throw e;
    }
};

var alertErrorMessage = function(e) {
    if(e.message) {
        alert(e.message);
    }
    throw e;
};

m.route(document.body, "/secrets", {
    "/login": { render: function() { return m(LoginForm); } },
    "/secrets": { render: function() { return m(Layout, m(SecretList)); } },
    "/secrets/:key": { render: function(vnode) { return m(Layout, vnode.attrs, m(SecretForm, vnode.attrs)); } },
    "/admin": { render: function() { return m(Layout, m(AdminMenu)); } },
    "/admin/groups": { render: function() { return m(Layout, m(GroupList)); } },
    "/admin/groups/:key": { render: function(vnode) { return m(Layout, vnode.attrs, m(GroupForm, vnode.attrs)); } },
    "/admin/users": { render: function() { return m(Layout, m(UserList)); } },
    "/admin/users/:key": { render: function(vnode) { return m(Layout, vnode.attrs, m(UserForm, vnode.attrs)); } },
    "/admin/profile": { render: function() { return m(Layout, m(Profile)); } },
    "/admin/backuprestore": { render: function() { return m(Layout, m(BackupRestore)); } },
    "/logout": { render: function() { Session.logout(); m.route.set("/login"); } }
});
