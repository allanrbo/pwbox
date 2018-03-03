var BackupRestore = {
    getCsv: function() {
        return m.request({
            method: "GET",
            url: "/api/csv",
            config: xhrConfig,
            extract: function(xhr) {
                if (xhr.status == 200) {
                    return xhr.responseText;
                }

                return JSON.parse(xhr.responseText);
            }
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
    }
}
