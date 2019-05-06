var SoftwareUpdatesOsLogPage = {
    oninit: function(vnode) {
        SoftwareUpdates.load(vnode.attrs.key);
    },

    view: function() {
        return [
            m("h2.content-subhead", "Operating system update log: " + vnode.attrs.key),

            m("pre.code", "hi")
        ];
    }
}
