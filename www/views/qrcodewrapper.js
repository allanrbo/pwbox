// Mithril component wrapping around the QRCode for JavaScript library 
var QrCodeWrapper = {
    updateExternalComponent: function(vnode) {
        if (vnode.state.data != vnode.attrs.data) {
            while (vnode.dom.firstChild) {
                vnode.dom.removeChild(vnode.dom.firstChild);
            }

            var o = new QRCode(vnode.dom, {
                width : 200,
                height : 200
            });
            o.makeCode(vnode.attrs.data);

            vnode.state.data = vnode.attrs.data;
        }
    },
    oncreate: function(vnode) {
        this.updateExternalComponent(vnode);
    },
    onupdate: function(vnode) {
        this.updateExternalComponent(vnode);
    },
    view: function() {
        return m("div");
    }
};
