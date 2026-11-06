document.addEventListener("DOMContentLoaded", () => {

    const navbar = document.querySelector(".navbar");
    const serviceButton = document.querySelector(".services-btn");
    const aboutButton = document.querySelector(".about-btn");
    const searchForm = document.querySelector("#center-search-form");
    const searchInput = document.querySelector("#center-search-input");
    const searchStatus = document.querySelector("#search-status");
    const centerCards = [...document.querySelectorAll(".center-card")];
    const centersSection = document.querySelector("#centers");
    const locationButton = document.querySelector("#use-location-button");
    const viewAllButton = document.querySelector(".view-all-btn");
    const filterChips = [...document.querySelectorAll(".filter-chip")];
    const mapDialog = document.querySelector("#map-dialog");
    const openMapButton = document.querySelector("#open-map-button");
    const closeMapButton = document.querySelector("#close-map-button");
    const availabilityModal = document.querySelector("#availability-modal");
    const availabilityTriggers = [...document.querySelectorAll(".availability-trigger")];
    const availabilityCloseButtons = [...document.querySelectorAll("[data-close-availability-modal='true']")];
    const availabilityLabel = document.querySelector("#availability-modal-label");
    const availabilityTitle = document.querySelector("#availability-modal-title");
    const availabilityCopy = document.querySelector("#availability-modal-copy");
    const availabilityDetails = document.querySelector("#availability-details");
    const availabilityLocation = document.querySelector("#availability-location");
    const availabilityHours = document.querySelector("#availability-hours");
    const availabilityActions = document.querySelector("#availability-actions");
    const availabilityBookingForm = document.querySelector("#availability-booking-form");
    const availabilityBookingCenter = document.querySelector("#availability-booking-center");
    const availabilityBookingLocation = document.querySelector("#availability-booking-location");
    const availabilityBookingHours = document.querySelector("#availability-booking-hours");
    const availabilityAsideCenter = document.querySelector("#availability-aside-center");
    const availabilityAsideLocation = document.querySelector("#availability-aside-location");
    const availabilityAsideHours = document.querySelector("#availability-aside-hours");
    const availabilityBookingDate = document.querySelector("#availability-booking-date");
    const availabilityBookingSession = document.querySelector("#availability-booking-session");
    const availabilityTimeSlots = document.querySelector("#availability-time-slots");
    const availabilityBookingTime = document.querySelector("#availability-booking-time");
    const availabilityDetailsStep = document.querySelector("#availability-booking-details-step");
    const availabilityPatientStep = document.querySelector("#availability-patient-step");
    const availabilitySummaryStep = document.querySelector("#availability-summary-step");
    const availabilityReviewButton = document.querySelector("#availability-review-button");
    const availabilitySummaryButton = document.querySelector("#availability-summary-button");
    const availabilityBackButton = document.querySelector("#availability-back-button");
    const activeFilters = new Set();
    const allHomeSidebarLinks = [...document.querySelectorAll(".home-sidebar-link")];
    const homeSidebarLinks = allHomeSidebarLinks.filter((link) => link.dataset.section);

    const closeMap = () => {
        if (mapDialog?.open) mapDialog.close();
    };

    const closeAvailabilityModal = () => {
        availabilityModal?.classList.remove("is-visible");
        availabilityModal?.setAttribute("aria-hidden", "true");
    };

    const setPatientStep = (isVisible) => {
        availabilityPatientStep?.toggleAttribute("hidden", !isVisible);
        availabilityPatientStep?.querySelectorAll("input, textarea").forEach((field) => {
            field.removeAttribute("disabled");
            field.readOnly = false;

            if (field.dataset.patientRequired === undefined && field.required) {
                field.dataset.patientRequired = "true";
            }

            field.required = isVisible && field.dataset.patientRequired === "true";
        });
    };

    const enablePatientField = (event) => {
        const field = event.target.closest?.("#availability-patient-step input, #availability-patient-step textarea");
        if (!field) return;

        field.removeAttribute("disabled");
        field.readOnly = false;
    };

    availabilityPatientStep?.addEventListener("pointerdown", enablePatientField, true);
    availabilityPatientStep?.addEventListener("focusin", enablePatientField, true);

    const showBookingDetails = () => {
        availabilityDetailsStep?.removeAttribute("hidden");
        availabilitySummaryStep?.setAttribute("hidden", "true");
        setPatientStep(false);
    };

    const showPatientStep = () => {
        availabilityDetailsStep?.setAttribute("hidden", "true");
        availabilitySummaryStep?.setAttribute("hidden", "true");
        setPatientStep(true);
        updateBookingAside();
    };

    const updateBookingAside = () => {
        if (!availabilityBookingForm) return;
        const values = new FormData(availabilityBookingForm);
        availabilityModal?.querySelectorAll(".availability-booking-aside [data-summary]").forEach((item) => {
            const key = item.dataset.summary;
            let value = values.get(key) ?? "";
            if (key === "time" && value) {
                value = availabilityTimeSlots?.querySelector(`[data-time="${CSS.escape(value)}"]`)?.textContent ?? value;
            }
            item.textContent = value || (key === "patient_name" ? "Not entered" : "Not selected");
        });
    };

    const showSummaryStep = () => {
        if (!availabilityBookingForm?.reportValidity()) return;

        const values = new FormData(availabilityBookingForm);
        availabilitySummaryStep?.querySelectorAll("[data-summary]").forEach((item) => {
            const key = item.dataset.summary;
            let value = values.get(key) ?? "";
            if (key === "time") {
                value = availabilityTimeSlots?.querySelector(`[data-time="${CSS.escape(value)}"]`)?.textContent ?? value;
            }
            item.textContent = value;
        });
        availabilityDetailsStep?.setAttribute("hidden", "true");
        setPatientStep(false);
        availabilitySummaryStep?.removeAttribute("hidden");
        updateBookingAside();
    };

    const timeSlots = [
        { value: "08:00", label: "8:00 AM", session: "Morning" },
        { value: "10:00", label: "10:00 AM", session: "Morning" },
        { value: "13:00", label: "1:00 PM", session: "Afternoon" },
        { value: "15:00", label: "3:00 PM", session: "Afternoon" },
        { value: "18:00", label: "6:00 PM", session: "Evening" }
    ];

    const parseTime = (value) => {
        const match = value.trim().match(/(\d{1,2}):(\d{2})\s*(am|pm)/i);
        if (!match) return null;
        let hour = Number(match[1]);
        if (match[3].toLowerCase() === "pm" && hour !== 12) hour += 12;
        if (match[3].toLowerCase() === "am" && hour === 12) hour = 0;
        return hour * 60 + Number(match[2]);
    };

    const renderTimeSlots = async () => {
        if (!availabilityTimeSlots) return;

        availabilityTimeSlots.replaceChildren();
        if (availabilityBookingTime) availabilityBookingTime.value = "";

        const hours = availabilityBookingHours?.value ?? "";
        const session = availabilityBookingSession?.value ?? "";
        const hourMatches = hours.match(/\d{1,2}:\d{2}\s*(?:am|pm)/gi) ?? [];
        const opening = parseTime(hourMatches[0] ?? "");
        const closing = parseTime(hourMatches[1] ?? "");
        let bookedTimes = [];
        if (availabilityBookingCenter?.value && availabilityBookingDate?.value) {
            try {
                const response = await fetch(`/booking/availability?center=${encodeURIComponent(availabilityBookingCenter.value)}&date=${encodeURIComponent(availabilityBookingDate.value)}`);
                if (response.ok) {
                    bookedTimes = (await response.json()).bookedTimes ?? [];
                }
            } catch (error) {
                bookedTimes = [];
            }
        }
        const availableSlots = timeSlots.filter((slot) => {
            const minutes = parseTime(slot.label);
            return opening !== null && closing !== null && minutes >= opening && minutes <= closing
                && (!session || slot.session === session);
        });

        if (!availableSlots.length) {
            const emptyMessage = document.createElement("p");
            emptyMessage.className = "availability-no-slots";
            emptyMessage.textContent = session ? "No sessions are available during this center's operating hours." : "Select a session to see available times.";
            availabilityTimeSlots.append(emptyMessage);
            return;
        }

        availableSlots.forEach((slot) => {
            const slotButton = document.createElement("button");
            slotButton.type = "button";
            const isBooked = bookedTimes.includes(slot.value);
            slotButton.className = `availability-time-slot ${isBooked ? "is-full" : "is-available"}`;
            slotButton.textContent = isBooked ? `${slot.label} - Fully booked` : slot.label;
            slotButton.dataset.time = slot.value;
            slotButton.disabled = isBooked;
            if (isBooked) return;
            slotButton.addEventListener("click", () => {
                availabilityTimeSlots.querySelectorAll(".availability-time-slot").forEach((item) => {
                    item.classList.remove("is-selected");
                    item.setAttribute("aria-pressed", "false");
                });
                slotButton.classList.add("is-selected");
                slotButton.setAttribute("aria-pressed", "true");
                if (availabilityBookingTime) availabilityBookingTime.value = slot.value;
                updateBookingAside();
            });
            availabilityTimeSlots.append(slotButton);
        });
    };

    const openAvailabilityModal = (button) => {
        if (!availabilityModal) return;

        const isLoggedIn = button.dataset.loggedIn === "true";
        availabilityModal.classList.toggle("has-booking-form", isLoggedIn);

        if (!isLoggedIn) {
            availabilityLabel.textContent = "Member access required";
            availabilityTitle.textContent = "Sign in or sign up first";
            availabilityCopy.textContent = "Please log in or create an account to check dialysis center availability and book an appointment.";
            if (availabilityDetails) availabilityDetails.hidden = true;
            if (availabilityBookingForm) availabilityBookingForm.hidden = true;
        } else {
            availabilityLabel.textContent = "Dialysis Center";
            availabilityTitle.textContent = button.dataset.center ?? "Dialysis Center";
            availabilityCopy.textContent = "View the availability of this dialysis center.";
            if (availabilityLocation) availabilityLocation.textContent = button.dataset.location ?? "";
            if (availabilityHours) availabilityHours.textContent = button.dataset.hours ?? "";
            if (availabilityDetails) availabilityDetails.hidden = false;
            if (availabilityBookingCenter) availabilityBookingCenter.value = button.dataset.center ?? "";
            if (availabilityBookingLocation) availabilityBookingLocation.value = button.dataset.location ?? "";
            if (availabilityBookingHours) availabilityBookingHours.value = button.dataset.hours ?? "";
            if (availabilityAsideCenter) availabilityAsideCenter.textContent = button.dataset.center ?? "Dialysis Center";
            if (availabilityAsideLocation) availabilityAsideLocation.textContent = button.dataset.location ?? "";
            if (availabilityAsideHours) availabilityAsideHours.textContent = button.dataset.hours ?? "";
            if (availabilityBookingDate) {
                availabilityBookingDate.min = new Date().toISOString().split("T")[0];
            }
            if (availabilityBookingForm) availabilityBookingForm.hidden = false;
            showBookingDetails();
            renderTimeSlots();
            updateBookingAside();
        }

        if (availabilityActions) {
            availabilityActions.hidden = false;
            availabilityActions.querySelectorAll("a").forEach((link) => {
                link.hidden = isLoggedIn;
            });
        }
        availabilityModal.classList.add("is-visible");
        availabilityModal.setAttribute("aria-hidden", "false");
    };

    availabilityTriggers.forEach((button) => {
        button.addEventListener("click", (event) => {
            event.preventDefault();
            openAvailabilityModal(button);
        });
    });

    availabilityBookingSession?.addEventListener("change", renderTimeSlots);
    availabilityBookingDate?.addEventListener("change", renderTimeSlots);
    availabilityBookingForm?.addEventListener("input", updateBookingAside);
    availabilityBookingForm?.addEventListener("change", updateBookingAside);
    availabilityReviewButton?.addEventListener("click", showPatientStep);
    availabilitySummaryButton?.addEventListener("click", showSummaryStep);
    availabilityBackButton?.addEventListener("click", showPatientStep);
    setPatientStep(false);

    openMapButton?.addEventListener("click", () => mapDialog?.showModal());
    closeMapButton?.addEventListener("click", closeMap);
    availabilityCloseButtons.forEach((button) => {
        button.addEventListener("click", closeAvailabilityModal);
    });
    availabilityModal?.addEventListener("click", (event) => {
        if (event.target === availabilityModal) closeAvailabilityModal();
    });
    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && availabilityModal?.classList.contains("is-visible")) {
            closeAvailabilityModal();
        }
    });
    mapDialog?.addEventListener("click", (event) => {
        if (event.target === mapDialog) closeMap();
    });

    const setActiveSidebarLink = (targetId) => {
        allHomeSidebarLinks.forEach((link) => {
            const isActive = link.dataset.section === targetId
                || (!targetId && link.getAttribute("href") === "/dashboard");
            link.classList.toggle("is-active", isActive);
            if (isActive) {
                link.setAttribute("aria-current", "location");
            } else {
                link.removeAttribute("aria-current");
            }
        });
    };

    allHomeSidebarLinks.forEach((link) => {
        link.addEventListener("click", () => {
            allHomeSidebarLinks.forEach((item) => item.classList.remove("is-active"));
            link.classList.add("is-active");
        });
    });

    homeSidebarLinks.forEach((link) => {
        link.addEventListener("click", (event) => {
            const target = document.querySelector(`#${link.dataset.section}`);
            if (!target) return;

            event.preventDefault();
            window.history.replaceState(null, "", `#${target.id}`);
            setActiveSidebarLink(target.id);
            target.classList.add("is-highlighted");
            target.scrollIntoView({ behavior: "smooth", block: "start" });

            window.setTimeout(() => target.classList.remove("is-highlighted"), 1400);
        });
    });

    const initialSection = window.location.hash.slice(1);
    if (homeSidebarLinks.some((link) => link.dataset.section === initialSection)) {
        setActiveSidebarLink(initialSection);
    }

    window.addEventListener("scroll", () => {
        navbar?.classList.toggle("is-sticky", window.scrollY > 80);
    }, { passive: true });

    serviceButton?.addEventListener("click", (event) => {
        event.preventDefault();
        document.querySelector("#services")?.scrollIntoView({
            behavior: "smooth"
        });
    });

    aboutButton?.addEventListener("click", (event) => {
        event.preventDefault();
        document.querySelector("#about")?.scrollIntoView({
            behavior: "smooth"
        });
    });

    const runCenterSearch = (scrollToResults = false) => {
        const query = searchInput?.value.trim().toLowerCase() ?? "";

        if (!query) {
            centerCards.forEach((card) => {
                card.hidden = true;
            });
            centersSection?.classList.add("has-no-results");
            if (searchStatus) searchStatus.textContent = "Enter a city or location to find available hospitals.";
            return;
        }

        let matchCount = 0;

        centerCards.forEach((card) => {
            const centerName = card.querySelector(".center-name")?.textContent?.toLowerCase() ?? "";
            const centerLocation = card.dataset.location ?? "";
            const searchableLocation = `${centerName} ${centerLocation}`;
            const matchesQuery = !query || searchableLocation.includes(query);
            const matchesFilters = [...activeFilters].every((filter) => Boolean(card.dataset[filter]));
            const isMatch = matchesQuery && matchesFilters;
            card.hidden = !isMatch;
            if (isMatch) matchCount += 1;
        });

        if (searchStatus) {
            searchStatus.textContent = matchCount
                ? `${matchCount} center${matchCount === 1 ? "" : "s"} found${query ? ` for "${query}"` : ""}.`
                : `No hospitals are currently available in "${query}". Hospitals in this area may be available soon.`;
        }

        centersSection?.classList.toggle("has-no-results", matchCount === 0);

        if (scrollToResults && matchCount > 0) {
            document.querySelector("#centers")?.scrollIntoView({ behavior: "smooth" });
        }
    };

    searchForm?.addEventListener("submit", (event) => {
        event.preventDefault();
        runCenterSearch(true);
    });

    if (viewAllButton?.getAttribute("href") === "#centers") {
        viewAllButton.addEventListener("click", (event) => {
        event.preventDefault();
        activeFilters.clear();
        centerCards.forEach((card) => {
            card.hidden = false;
        });
        centersSection?.classList.remove("has-no-results");
        filterChips.forEach((chip) => {
            chip.classList.remove("is-active");
            chip.setAttribute("aria-pressed", "false");
        });
        if (searchInput) searchInput.value = "";
        if (searchStatus) searchStatus.textContent = `Showing all ${centerCards.length} available centers.`;
        document.querySelector("#centers")?.scrollIntoView({ behavior: "smooth" });
        });
    }

    locationButton?.addEventListener("click", () => {
        if (!navigator.geolocation) {
            if (searchStatus) searchStatus.textContent = "Location is not available in this browser.";
            return;
        }

        locationButton.textContent = "Locating...";
        navigator.geolocation.getCurrentPosition(
            () => {
                locationButton.textContent = "Location selected";
                if (searchStatus) searchStatus.textContent = "Showing centers near your current location.";
            },
            () => {
                locationButton.textContent = "Use my location";
                if (searchStatus) searchStatus.textContent = "We could not access your location. Search by city instead.";
            }
        );
    });

    filterChips.forEach((chip) => {
        chip.addEventListener("click", () => {
            const filter = chip.dataset.filter;
            const isActive = activeFilters.has(filter);

            if (isActive) {
                activeFilters.delete(filter);
            } else {
                activeFilters.add(filter);
            }

            chip.classList.toggle("is-active", !isActive);
            chip.setAttribute("aria-pressed", String(!isActive));
            runCenterSearch(false);
        });
    });

});