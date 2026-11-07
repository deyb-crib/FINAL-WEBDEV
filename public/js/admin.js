(() => {
    const toggle = document.querySelector(".admin-menu-toggle");
    const sidebar = document.querySelector(".admin-sidebar");
    const backdrop = document.querySelector(".admin-sidebar-backdrop");

    if (!toggle || !sidebar || !backdrop) {
        return;
    }

    const close = () => {
        sidebar.classList.remove("is-open");
        backdrop.hidden = true;
        toggle.setAttribute("aria-expanded", "false");
    };

    toggle.addEventListener("click", () => {
        const isOpen = sidebar.classList.toggle("is-open");
        backdrop.hidden = !isOpen;
        toggle.setAttribute("aria-expanded", String(isOpen));
    });
    backdrop.addEventListener("click", close);
    sidebar.querySelectorAll("a").forEach((link) => link.addEventListener("click", close));
})();
