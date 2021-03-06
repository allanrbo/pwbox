var Session = {
    current: {},

    login: function(id) {
        return m.request({
            method: "POST",
            url: "/api/authenticate",
            data: {
                username: Session.current.username,
                password: Session.current.password,
                otp: Session.current.otp,
                trustDevice: Session.current.trustDevice,
                trustedDeviceName: Session.current.trustedDeviceName,
                trustedDeviceToken: Session.current.trustedDeviceToken
            }
        })
        .then(function(data) {
            localStorage.setItem("username", Session.current.username);
            localStorage.setItem("token", data.token);
            Session.current.password = null;
            Session.current.otp = null;

            var now = (new Date()).getTime();
            localStorage.setItem("tokenExpiry", now + data.tokenExpiryMinutes*60*1000);

            if (data.trustedDevice) {
                localStorage.setItem("trustedDevice", JSON.stringify(data.trustedDevice));
            }

            Session.setupLogoutOnSessionExpire();
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage)
        .then(Session.refreshProfile);
    },

    logout: function(id) {
        // Best effort. Ignore errors.
        m.request({
            method: "POST",
            url: "/api/logout",
            config: xhrConfig
        });

        localStorage.removeItem("token");
        localStorage.removeItem("profile");
        localStorage.removeItem("tokenExpiry");

        if (!Session.getTrustedDevice()) {
            localStorage.removeItem("username");
        }

        Session.current.username = null;
        Session.current.password = null;

        Secret.current = {};
        Secret.list = [];
    },

    removeTrustedDeviceById: function(id) {
        return m.request({
            method: "POST",
            url: "/api/removetrusteddevicebyid",
            data: {
                id: id
            },
            config: xhrConfig
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    },

    removeTrustedDeviceByIdUnauthenticated: function(id, username, token) {
        // Best effort. Ignore errors.
        return m.request({
            method: "POST",
            url: "/api/removetrusteddevicebyidunauthenticated",
            data: {
                id: id,
                username: username,
                token: token
            },
            config: xhrConfig
        });
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
    },

    getTrustedDevice: function() {
        return JSON.parse(localStorage.getItem("trustedDevice"));
    },

    removeTrustedDevice: function() {
        var trustedDevice = Session.getTrustedDevice();
        Session.removeTrustedDeviceByIdUnauthenticated(trustedDevice.id, Session.getUsername(), trustedDevice.token);

        return localStorage.removeItem("trustedDevice");
    },

    getSessionRemainingTimeSecs: function() {
        var now = (new Date()).getTime();
        var tokenExpiry = localStorage.getItem("tokenExpiry");
        if (tokenExpiry === null) {
            return 0;
        }

        var msRemaining = tokenExpiry - now;
        var secsRemaining = Math.floor(msRemaining/1000);
        return secsRemaining;
    },

    getSessionRemainingTimeStr: function() {
        var secsRemaining = Session.getSessionRemainingTimeSecs();
        var minsRemaining = Math.floor(secsRemaining/60);
        var secsRemainingRel = (secsRemaining - minsRemaining*60);

        // Prepend the minutes and seconds with 0, so it looks like 05:05
        var pad = "00";
        var secsStr = "" + secsRemainingRel;
        secsStr = pad.substring(0, pad.length - secsStr.length) + secsStr;
        var minsStr = "" + minsRemaining;
        minsStr = pad.substring(0, pad.length - minsStr.length) + minsStr;

        return minsStr + ":" + secsStr;
    },

    setupLogoutOnSessionExpire: function() {
        var ref = {};
        var f = function() {
            // Bypass Mithril and get sessionTimeRemaining div directly to avoid wasteful constant redraw
            var sessionTimeRemainingDiv = document.getElementById("sessionTimeRemaining");
            if (sessionTimeRemainingDiv) {
                sessionTimeRemaining.innerText = "Time remaining: " + Session.getSessionRemainingTimeStr();
            }

            if (Session.getSessionRemainingTimeSecs() <= 0) {
                clearInterval(ref.r);

                if (Session.getToken()) {
                    Session.logout();
                }

                if (m.route.get() != "/login") {
                    m.route.set("/login");
                }
            }
        };
        f();
        ref.r = setInterval(f, 1000);
    }
}
