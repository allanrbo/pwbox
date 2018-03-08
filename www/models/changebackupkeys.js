var ChangeBackupKeys = {
    current: {},

    secretSave: function() {
        var method = "POST";
        var url = "/api/changesecretsbackupkey";

        return m.request({
            method: method,
            url: url,
            data: {},
            config: xhrConfig
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    },

    secretDisable: function() {
        var method = "POST";
        var url = "/api/changesecretsbackupkey";

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
