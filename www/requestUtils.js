var xhrConfig = function(xhr) {
    xhr.setRequestHeader("Content-Type", "application/json");
    xhr.setRequestHeader("Authorization", "Bearer " + Session.getToken());
};

var xhrConfigBinary = function(xhr) {
    xhrConfig(xhr);
    xhr.responseType = "arraybuffer";
};

var xhrExtractBinary = function(xhr) {
    if (xhr.status == 200) {
        return xhr.response;
    }

    var responseText = (new TextDecoder()).decode(xhr.response);
    var error = new Error(responseText !== "" ? responseText : "Unknown error");
    try {
        error = JSON.parse(responseText)
    } catch (e) { }

    throw error;
}

var handleUnauthorized = function(e) {
    if(e.status == "unauthorized") {
        Session.logout();
        m.route.set('/login');
        e.message = null;
    }
    throw e;
};

var alertErrorMessage = function(e) {
    if(e.message) {
        alert(e.message);
    }
    throw e;
};
