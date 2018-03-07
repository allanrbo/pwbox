var BackupRestore = {
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
    }
}
