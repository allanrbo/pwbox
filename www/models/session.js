var Session = {
    current: {},

    login: function(id) {
        return m.request({
            method: "POST",
            url: "/api/authenticate",
            data: {
                username: Session.current.username,
                password: Session.current.password
            }
        })
        .then(function(data) {
            localStorage.setItem("username", Session.current.username);
            localStorage.setItem("token", data.token);
            Session.current.password = null;
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    },

    logout: function(id) {
        localStorage.setItem("username", null);
        localStorage.setItem("token", null);

        Session.current.username = null;
        Session.current.password = null;

        Secret.current = {};
        Secret.list = [];
    },

    getToken: function() {
        return localStorage.getItem("token");
    },

    getUsername: function() {
        return localStorage.getItem("username");
    }
}
