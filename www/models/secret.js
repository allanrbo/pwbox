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
    },

    delete: function() {
        var method = "DELETE";
        return m.request({
            method: method,
            url: "/api/secret/" + Secret.current.id,
            config: xhrConfig
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    },

    toggleRecipient: function(username) {
        var recipientIndex = Secret.current.recipients.indexOf(username);
        if (recipientIndex > -1) {
            Secret.current.recipients.splice(recipientIndex, 1);
        } else {
            Secret.current.recipients.push(username);
        }
    }
}
