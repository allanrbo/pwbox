var User = {
    list: [],

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
    }
}
