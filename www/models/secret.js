var Secret = {
    list: [],
    listLoaded: false,
    current: {},

    loadList: function() {
        Secret.listLoaded = false;
        return m.request({
            method: "GET",
            url: "/api/secret",
            config: xhrConfig
        })
        .then(function(result) {
            Secret.list = result;
            Secret.listLoaded = true;
        })
        .catch(function(e) {
            Secret.listLoaded = true;
            throw e;
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

    toggleGroup: function(group) {
        if (Secret.current.groups == null) {
            Secret.current.groups = [];
        }

        var groupIndex = Secret.current.groups.indexOf(group);
        if (groupIndex > -1) {
            Secret.current.groups.splice(groupIndex, 1);
        } else {
            Secret.current.groups.push(group);
        }
    }
}
