var UserChangeOtpKey = {
    current: {},

    save: function() {
        var method = "POST";
        var url = "/api/changeotpkey";

        return m.request({
            method: method,
            url: url,
            data: {},
            config: xhrConfig
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    },

    disable: function() {
        var method = "POST";
        var url = "/api/changeotpkey";

        return m.request({
            method: method,
            url: url,
            data: {disable: true},
            config: xhrConfig
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    }
}
