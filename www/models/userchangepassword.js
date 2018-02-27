var UserChangePassword = {
    current: {},

    save: function() {
        var method = "POST";
        var url = "/api/changepassword";

        return m.request({
            method: method,
            url: url,
            data: UserChangePassword.current,
            config: xhrConfig
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage)
        .then(function() {
            Session.current.username = Session.getUsername();
            Session.current.password = UserChangePassword.current.password;
            return Session.login();
        });
    }
}
