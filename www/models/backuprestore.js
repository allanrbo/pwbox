var BackupRestore = {
    getCsv: function() {
        return m.request({
            method: "GET",
            url: "/api/csv",
            config: xhrConfig,
            deserialize: function(value) {return value}
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
            serialize: function(value) {return value}
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    }
}
