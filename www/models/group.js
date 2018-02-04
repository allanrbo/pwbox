var Group = {
    list: [],
    current: {},

    loadList: function() {
        return m.request({
            method: "GET",
            url: "/api/group",
            config: xhrConfig
        })
        .then(function(result) {
            Group.list = result;
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    },

    load: function(name) {
        return m.request({
            method: "GET",
            url: "/api/group/" + name,
            config: xhrConfig
        })
        .then(function(result) {
            Group.current = result;
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    },

    save: function() {
        var method = "POST";
        var url = "/api/group";
        if (Group.current.modified) {
            method = "PUT";
            url = "/api/group/" + Group.current.name;
        }

        return m.request({
            method: method,
            url: url,
            data: Group.current,
            config: xhrConfig
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    },

    delete: function() {
        var method = "DELETE";
        return m.request({
            method: method,
            url: "/api/group/" + Group.current.name,
            config: xhrConfig
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    },

    toggleMember: function(username) {
        if (Group.current.members == null) {
            Group.current.members = [];
        }

        var memberIndex = Group.current.members.indexOf(username);
        if (memberIndex > -1) {
            Group.current.members.splice(memberIndex, 1);
        } else {
            Group.current.members.push(username);
        }
    }
}
