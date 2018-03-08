var BackupRestore = {
    backupTokenStatus: {},
    secretsBackupToken: {},
    usersBackupToken: {},

    getCsv: function() {
        return m.request({
            method: "GET",
            url: "/api/csv",
            config: xhrConfigBinary,
            extract: xhrExtractBinary
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    },

    putCsv: function(data) {
        return m.request({
            method: "PUT",
            url: "/api/csv",
            data: data,
            config: xhrConfig,
            serialize: function(value) { return value; }
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    },

    getTarSecrets: function() {
        return m.request({
            method: "GET",
            url: "/api/backuptarsecrets",
            config: xhrConfigBinary,
            extract: xhrExtractBinary
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    },

    putTarSecrets: function(data) {
        return m.request({
            method: "PUT",
            url: "/api/backuptarsecrets",
            data: data,
            config: xhrConfig,
            serialize: function(value) { return value; }
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    },

    getTarUsers: function() {
        return m.request({
            method: "GET",
            url: "/api/backuptarusers",
            config: xhrConfigBinary,
            extract: xhrExtractBinary
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    },

    putTarUsers: function(data) {
        return m.request({
            method: "PUT",
            url: "/api/backuptarusers",
            data: data,
            config: xhrConfig,
            serialize: function(value) { return value; }
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    },

    loadBackupTokenStatus: function() {
        var method = "GET";
        var url = "/api/backuptokens";

        return m.request({
            method: method,
            url: url,
            config: xhrConfig
        })
        .then(function(result) {
            BackupRestore.backupTokenStatus = result;
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    },

    secretsBackupTokenSave: function() {
        var method = "POST";
        var url = "/api/changebackuptoken/secrets";

        return m.request({
            method: method,
            url: url,
            data: {},
            config: xhrConfig
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    },

    secretsBackupTokenDisable: function() {
        var method = "POST";
        var url = "/api/changebackuptoken/secrets";

        return m.request({
            method: method,
            url: url,
            data: {disable: true},
            config: xhrConfig
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    },

    usersBackupTokenSave: function() {
        var method = "POST";
        var url = "/api/changebackuptoken/users";

        return m.request({
            method: method,
            url: url,
            data: {},
            config: xhrConfig
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    },

    usersBackupTokenDisable: function() {
        var method = "POST";
        var url = "/api/changebackuptoken/users";

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
