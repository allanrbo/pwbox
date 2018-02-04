var User = {
    list: [],
    current: {},

    loadList: function() {
        return m.request({
            method: "GET",
            url: "/api/user",
            config: xhrConfig
        })
        .then(function(result) {
            User.list = result;
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    },

    save: function() {
        var method = "POST";
        var url = "/api/user";
        if (User.current.id) {
            method = "PUT";
            url = "/api/user/" + User.current.id;
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
