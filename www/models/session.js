var Session = {
    current: {},

    login: function(id) {
        return m.request({
            method: "POST",
            url: "/api/authenticate",
            data: {
                username: Session.current.username,
                password: Session.current.password,
                otp: Session.current.otp
            }
        })
        .then(function(data) {
            localStorage.setItem("username", Session.current.username);
            localStorage.setItem("token", data.token);
            Session.current.password = null;
            Session.current.otp = null;
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage)
        .then(Session.refreshProfile);
    },

    logout: function(id) {
        localStorage.setItem("username", null);
        localStorage.setItem("token", null);
        localStorage.setItem("profile", null);

        Session.current.username = null;
        Session.current.password = null;

        Secret.current = {};
        Secret.list = [];
    },

    refreshProfile: function() {
        return User.load(Session.getUsername())
        .then(function() {
            localStorage.setItem("profile", JSON.stringify(User.current));
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    },

    getToken: function() {
        return localStorage.getItem("token");
    },

    getUsername: function() {
        return localStorage.getItem("username");
    },

    getProfile: function() {
        return JSON.parse(localStorage.getItem("profile"));
    }
}
