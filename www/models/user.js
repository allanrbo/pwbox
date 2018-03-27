var User = {
    list: [],
    listLoaded: false,
    current: {},

    loadList: function() {
        User.listLoaded = false;
        return m.request({
            method: "GET",
            url: "/api/user",
            config: xhrConfig
        })
        .then(function(result) {
            User.list = result;
            User.listLoaded = true;
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    },

    load: function(id) {
        return m.request({
            method: "GET",
            url: "/api/user/" + id,
            config: xhrConfig
        })
        .then(function(result) {
            User.current = result;
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    },

    save: function() {
        var method = "POST";
        var url = "/api/user";
        if (User.current.modified) {
            method = "PUT";
            url = "/api/user/" + User.current.username;
        }

        return m.request({
            method: method,
            url: url,
            data: User.current,
            config: xhrConfig
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    }
}
