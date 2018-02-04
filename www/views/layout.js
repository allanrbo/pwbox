var Layout = {
    view: function(vnode) {

        var menu = m("#menu", [
            m(".pure-menu", [
                m("a.pure-menu-heading[href=#]", "PwBox"),

                m("ul.pure-menu-list", [
                    m("li.pure-menu-item.pure-menu-selected", m("a.pure-menu-link[href=/secrets]", {oncreate: m.route.link}, "Secrets")),
                    m("li.pure-menu-item", m("a.pure-menu-link[href=/admin]", {oncreate: m.route.link}, "Admin")),
                    m("li.pure-menu-item.menu-item-divided"),
                    m("li.pure-menu-item", m("a.pure-menu-link[href=/logout]", {oncreate: m.route.link}, "Log out")),
                ])
            ])
        ]);

        // Hamburger button for small-screen mode
        var hamburgerLink = m("a#menuLink.menu-link[href=#menu]", m("span"));

        var contentArea = m("#main", m(".content", vnode.children));

        layout = m("#layout", [
            hamburgerLink,
            menu,
            contentArea
        ]);

        // This hamburger-menu logic is based on Pure.css's responsive side menu ui.js
        var toggleMenu = function(e) {
            e.preventDefault();
            layout.dom.classList.toggle("active");
            menu.dom.classList.toggle("active");
            hamburgerLink.dom.classList.toggle("active");
        };

        hamburgerLink.attrs.onclick = toggleMenu;

        contentArea.attrs.onclick = function(e) {
            if (menu.dom.classList.contains("active")) {
                toggleMenu(e);
            }
        };

        return layout;
    }
}
