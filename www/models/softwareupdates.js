var SoftwareUpdates = {
    hostInfo: {},
    updateOsCheckResult: {},
    updateOsPerformResult: {},
    updateOsLogsListLoaded: false,
    updateOsLogsList: {},

    getHostInfo: function() {
        return m.request({
            method: "GET",
            url: "/api/hostinfo",
            config: xhrConfig,
        })
        .then(function(result) {
            SoftwareUpdates.hostInfo = result;
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    },

    updateOsCheck: function() {
        return m.request({
            method: "POST",
            url: "/api/updateoscheck",
            config: xhrConfig,
        })
        .then(function(result) {
            SoftwareUpdates.updateOsCheckResult = result;
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    },

    updateOsPerform: function() {
        return m.request({
            method: "POST",
            url: "/api/updateosperform",
            config: xhrConfig,
        })
        .then(function(result) {
            SoftwareUpdates.updateOsPerformResult = result;
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    },

    loadUpdateOsLogsList: function() {
        return m.request({
            method: "GET",
            url: "/api/updateoslogs",
            config: xhrConfig,
        })
        .then(function(result) {
            SoftwareUpdates.updateOsLogsList = result;
            SoftwareUpdates.updateOsLogsListLoaded = true;
        })
        .catch(handleUnauthorized)
        .catch(alertErrorMessage);
    }

    // getUpdateOsPerformStatus: function(updateJobId) {
    //     return m.request({
    //         method: "GET",
    //         url: "/api/updateosperformstatus/" + updateJobId,
    //         config: xhrConfig,
    //     })
    //     .then(function(result) {
    //         SoftwareUpdates.updateOsPerformStatusResult = result;
    //     })
    //     .catch(handleUnauthorized)
    //     .catch(alertErrorMessage);
    // }
}
