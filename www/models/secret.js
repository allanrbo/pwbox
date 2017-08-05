var Secret = {
    list: [],
    current: {},

    loadList: function() {
        return m.request({
            method: "GET",
            url: "/api/secret",
            config: xhrConfig
        })
        .then(function(result) {
            Secret.list = result;
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    },

    load: function(id) {
        return m.request({
            method: "GET",
            url: "/api/secret/" + id,
            config: xhrConfig
        })
        .then(function(result) {
            Secret.current = result;
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    },

    save: function() {
        var method = "POST";
        var url = "/api/secret";
        if (Secret.current.id) {
            method = "PUT";
            url = "/api/secret/" + Secret.current.id;
        }

        return m.request({
            method: method,
            url: url,
            data: Secret.current,
            config: xhrConfig
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    }
}
