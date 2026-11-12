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

(() => {
    const form = document.querySelector("[data-user-form]");
    const roleSelect = form?.querySelector("[data-role-select]");
    const centerAssignment = form?.querySelector("[data-center-assignment]");

    if (!roleSelect || !centerAssignment) {
        return;
    }

    const updateRoleGuide = () => {
        centerAssignment.hidden = !["hospital_admin", "hospital_staff"].includes(roleSelect.value);
        form.querySelectorAll("[data-role-card]").forEach((card) => {
            card.classList.toggle("is-selected", card.dataset.roleCard === roleSelect.value);
        });
    };

    roleSelect.addEventListener("change", updateRoleGuide);
    updateRoleGuide();
})();
