const setupNavState = () => {
    const nav = document.getElementById('site-nav');

    if (!nav) {
        return;
    }

    const updateNav = () => {
        if (window.scrollY > 24) {
            nav.classList.add('bg-[#f5efe7]/88', 'backdrop-blur-xl', 'shadow-lg', 'shadow-black/5');
        } else {
            nav.classList.remove('bg-[#f5efe7]/88', 'backdrop-blur-xl', 'shadow-lg', 'shadow-black/5');
        }
    };

    updateNav();
    window.addEventListener('scroll', updateNav, { passive: true });
};

const setupMobileMenu = () => {
    const toggle = document.getElementById('menu-toggle');
    const menu = document.getElementById('mobile-menu');

    if (!toggle || !menu) {
        return;
    }

    const closeMenu = () => {
        menu.classList.add('hidden');
        toggle.setAttribute('aria-expanded', 'false');
    };

    toggle.addEventListener('click', () => {
        const isOpen = !menu.classList.contains('hidden');

        if (isOpen) {
            closeMenu();
            return;
        }

        menu.classList.remove('hidden');
        toggle.setAttribute('aria-expanded', 'true');
    });

    menu.querySelectorAll('a[href^="#"]').forEach((link) => {
        link.addEventListener('click', closeMenu);
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth >= 768) {
            closeMenu();
        }
    });
};

const setupSmoothScroll = () => {
    document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
        anchor.addEventListener('click', (event) => {
            const targetId = anchor.getAttribute('href');

            if (!targetId || targetId === '#') {
                return;
            }

            const target = document.querySelector(targetId);

            if (!target) {
                return;
            }

            event.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
};

const setupActiveSection = () => {
    const sections = [...document.querySelectorAll('section[id]')];
    const links = [...document.querySelectorAll('.nav-link')];

    if (!sections.length || !links.length) {
        return;
    }

    const setActive = (id) => {
        links.forEach((link) => {
            const isActive = link.getAttribute('href') === `#${id}`;
            link.classList.toggle('is-active', isActive);
        });
    };

    const observer = new IntersectionObserver((entries) => {
        const visibleEntry = entries
            .filter((entry) => entry.isIntersecting)
            .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];

        if (visibleEntry?.target?.id) {
            setActive(visibleEntry.target.id);
        }
    }, {
        rootMargin: '-35% 0px -45% 0px',
        threshold: [0.2, 0.45, 0.7],
    });

    sections.forEach((section) => observer.observe(section));
};

const setupReveal = () => {
    const elements = document.querySelectorAll('.reveal');

    if (!elements.length) {
        return;
    }

    elements.forEach((element) => {
        element.classList.add('translate-y-6', 'opacity-0', 'transition', 'duration-700');
    });

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }

            entry.target.classList.remove('translate-y-6', 'opacity-0');
            entry.target.classList.add('translate-y-0', 'opacity-100');
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.2 });

    elements.forEach((element) => observer.observe(element));
};

const setupRatePanels = () => {
    const toggles = [...document.querySelectorAll('[data-rate-toggle]')];

    if (!toggles.length) {
        return;
    }

    toggles.forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const panelId = toggle.getAttribute('aria-controls');
            const panel = panelId ? document.getElementById(panelId) : null;
            const icon = toggle.querySelector('[data-rate-icon]');

            if (!panel) {
                return;
            }

            const isOpen = toggle.getAttribute('aria-expanded') === 'true';

            toggles.forEach((otherToggle) => {
                const otherPanelId = otherToggle.getAttribute('aria-controls');
                const otherPanel = otherPanelId ? document.getElementById(otherPanelId) : null;
                const otherIcon = otherToggle.querySelector('[data-rate-icon]');

                otherToggle.setAttribute('aria-expanded', 'false');
                otherPanel?.classList.add('hidden');
                otherIcon?.classList.remove('rotate-180', 'bg-[#c49c5b]', 'text-[#18130f]');
                otherIcon?.classList.add('bg-[#163129]', 'text-white');
            });

            if (isOpen) {
                return;
            }

            toggle.setAttribute('aria-expanded', 'true');
            panel.classList.remove('hidden');
            icon?.classList.add('rotate-180', 'bg-[#c49c5b]', 'text-[#18130f]');
            icon?.classList.remove('bg-[#163129]', 'text-white');
        });
    });
};

const setupBookingAvailability = () => {
    const form = document.querySelector('[data-booking-form]');
    const layout = document.querySelector('[data-room-layout]');
    const modal = document.querySelector('[data-room-modal]');
    const bookingModal = document.querySelector('[data-booking-modal]');

    if (!form || !layout || !modal || !bookingModal) {
        return;
    }

    const availabilityUrl = form.getAttribute('data-availability-url');
    const unavailableDatesUrl = form.getAttribute('data-unavailable-dates-url');
    const quoteUrl = form.getAttribute('data-quote-url');
    const layoutUrl = layout.getAttribute('data-layout-url');
    const roomSelect = form.querySelector('[data-room-select]');
    const bookingDateInput = form.querySelector('[data-booking-date]');
    const startTimeSelect = form.querySelector('[data-start-time-select]');
    const endTimeSelect = form.querySelector('[data-end-time-select]');
    const durationDisplay = form.querySelector('[data-duration-display]');
    const downpaymentInput = form.querySelector('[data-downpayment-input]');
    const paymentMethodSelect = form.querySelector('[data-payment-method]');
    const quoteTotal = form.querySelector('[data-quote-total]');
    const quoteMinimumDownpayment = form.querySelector('[data-quote-minimum-downpayment]');
    const quoteBalance = form.querySelector('[data-quote-balance]');
    const quoteMeta = form.querySelector('[data-quote-meta]');
    const paymentGcash = form.querySelector('[data-payment-gcash]');
    const paymentBank = form.querySelector('[data-payment-bank]');
    const paymentInstructions = form.querySelector('[data-payment-instructions]');
    const roomMeta = form.querySelector('[data-selected-room-meta]');
    const message = form.querySelector('[data-availability-message]');
    const messageBody = form.querySelector('[data-availability-message-body]');
    const spinner = form.querySelector('[data-availability-spinner]');
    const unavailableDatesMessage = form.querySelector('[data-unavailable-dates-message]');
    const unavailableDatesList = form.querySelector('[data-unavailable-dates-list]');
    const unavailableDatesHorizon = Number(form.getAttribute('data-unavailable-dates-horizon') || '14');
    const minimumDurationMinutes = Number(form.getAttribute('data-minimum-duration') || '60');
    const layoutDateInput = layout.querySelector('[data-layout-date]');
    const roomButtons = [...layout.querySelectorAll('[data-layout-room]')];
    const bookingOpenButtons = [...document.querySelectorAll('[data-booking-open]')];
    const bookingClose = bookingModal.querySelector('[data-booking-close]');
    const modalClose = modal.querySelector('[data-room-modal-close]');
    const roomDetailName = modal.querySelector('[data-room-detail-name]');
    const roomDetailType = modal.querySelector('[data-room-detail-type]');
    const roomDetailMeta = modal.querySelector('[data-room-detail-meta]');
    const roomDetailStatus = modal.querySelector('[data-room-detail-status]');
    const roomDetailNextSlot = modal.querySelector('[data-room-detail-next-slot]');
    const roomDetailAvailableCount = modal.querySelector('[data-room-detail-available-count]');
    const roomDetailBookedCount = modal.querySelector('[data-room-detail-booked-count]');
    const roomDetailAvailable = modal.querySelector('[data-room-detail-available]');
    const roomDetailBooked = modal.querySelector('[data-room-detail-booked]');
    const roomDetailTimeline = modal.querySelector('[data-room-detail-timeline]');

    if (!availabilityUrl || !unavailableDatesUrl || !quoteUrl || !layoutUrl || !roomSelect || !bookingDateInput || !startTimeSelect || !endTimeSelect || !durationDisplay || !downpaymentInput || !paymentMethodSelect || !quoteTotal || !quoteMinimumDownpayment || !quoteBalance || !quoteMeta || !paymentGcash || !paymentBank || !paymentInstructions || !roomMeta || !message || !messageBody || !spinner || !unavailableDatesMessage || !unavailableDatesList || !layoutDateInput || !roomButtons.length || !bookingOpenButtons.length || !bookingClose || !modalClose || !roomDetailName || !roomDetailType || !roomDetailMeta || !roomDetailStatus || !roomDetailNextSlot || !roomDetailAvailableCount || !roomDetailBookedCount || !roomDetailAvailable || !roomDetailBooked || !roomDetailTimeline) {
        return;
    }

    let roomMap = new Map();
    let unavailableDates = new Set();
    let currentQuote = null;

    const syncBodyScrollLock = () => {
        const shouldLock = bookingModal.getAttribute('aria-hidden') === 'false' || modal.getAttribute('aria-hidden') === 'false';
        document.body.classList.toggle('overflow-hidden', shouldLock);
    };

    const setLoading = (isLoading) => {
        spinner.classList.toggle('hidden', !isLoading);
    };

    const formatDateLabel = (value) => {
        if (!value) {
            return '';
        }

        const parsedDate = new Date(`${value}T00:00:00`);

        return Number.isNaN(parsedDate.getTime())
            ? value
            : new Intl.DateTimeFormat('en-PH', {
                month: 'long',
                day: 'numeric',
                year: 'numeric',
            }).format(parsedDate);
    };

    const formatCurrency = (value) => `Php ${Number(value || 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;

    const parseTimeToMinutes = (value) => {
        if (!value || !value.includes(':')) {
            return null;
        }

        const [hour, minute] = value.split(':').map((part) => Number(part));

        if (Number.isNaN(hour) || Number.isNaN(minute)) {
            return null;
        }

        return (hour * 60) + minute;
    };

    const humanizeDuration = (startValue, endValue) => {
        const startMinutes = parseTimeToMinutes(startValue);
        const endMinutesBase = parseTimeToMinutes(endValue);

        if (startMinutes === null || endMinutesBase === null) {
            return '';
        }

        let endMinutes = endMinutesBase;

        if (endMinutes <= startMinutes) {
            endMinutes += 24 * 60;
        }

        const totalMinutes = endMinutes - startMinutes;
        const hours = Math.floor(totalMinutes / 60);
        const minutes = totalMinutes % 60;
        const parts = [];

        if (hours > 0) {
            parts.push(`${hours} ${hours === 1 ? 'hour' : 'hours'}`);
        }

        if (minutes > 0) {
            parts.push(`${minutes} mins`);
        }

        return parts.join(' ');
    };

    const setMessage = (text, tone = 'neutral') => {
        messageBody.textContent = text;
        message.classList.remove(
            'text-red-700',
            'bg-red-500/10',
            'border-red-400/30',
            'text-emerald-700',
            'bg-emerald-500/10',
            'border-emerald-400/30',
            'text-[#5f5449]',
            'bg-[#f7f2eb]',
            'border-[#163129]/8',
        );

        message.classList.add('border');

        if (tone === 'error') {
            message.classList.add('text-red-700', 'bg-red-500/10', 'border-red-400/30');
        }

        if (tone === 'success') {
            message.classList.add('text-emerald-700', 'bg-emerald-500/10', 'border-emerald-400/30');
        }

        if (tone === 'neutral') {
            message.classList.add('text-[#5f5449]', 'bg-[#f7f2eb]', 'border-[#163129]/8');
        }
    };

    const resetStartTimes = (placeholder) => {
        startTimeSelect.innerHTML = '';
        const option = document.createElement('option');
        option.value = '';
        option.textContent = placeholder;
        startTimeSelect.append(option);
        startTimeSelect.disabled = true;
        startTimeSelect.dataset.previousValue = '';
    };

    const resetEndTimes = (placeholder) => {
        endTimeSelect.innerHTML = '';
        const option = document.createElement('option');
        option.value = '';
        option.textContent = placeholder;
        endTimeSelect.append(option);
        endTimeSelect.disabled = true;
        endTimeSelect.dataset.previousValue = '';
    };

    const resetDuration = (text = `Choose a start and end time of at least ${minimumDurationMinutes / 60} hour to see the total duration.`) => {
        durationDisplay.value = text;
    };

    const resetQuote = (text = 'Choose a room, date, start time, and end time first to load your live rate summary.') => {
        quoteTotal.textContent = 'Php 0.00';
        quoteMinimumDownpayment.textContent = 'Php 0.00';
        quoteBalance.textContent = 'Php 0.00';
        quoteMeta.textContent = text;
        currentQuote = null;
    };

    const updateBalanceFromInput = () => {
        if (!currentQuote) {
            quoteBalance.textContent = 'Php 0.00';
            return;
        }

        const totalAmount = Number(currentQuote.total_amount || 0);
        const minimumAmount = Number(currentQuote.minimum_downpayment_amount || 0);
        let enteredAmount = Number(downpaymentInput.value || 0);

        if (Number.isNaN(enteredAmount) || enteredAmount < minimumAmount) {
            enteredAmount = minimumAmount;
        }

        if (enteredAmount > totalAmount) {
            enteredAmount = totalAmount;
        }

        quoteBalance.textContent = formatCurrency(Math.max(0, totalAmount - enteredAmount));
    };

    const updatePaymentDestination = () => {
        const method = paymentMethodSelect.value;

        paymentGcash.classList.toggle('hidden', method !== 'gcash');
        paymentBank.classList.toggle('hidden', method !== 'bank_transfer');
    };

    const updateDurationDisplay = () => {
        const selectedEndOption = endTimeSelect.options[endTimeSelect.selectedIndex];

        if (!startTimeSelect.value || !endTimeSelect.value) {
            resetDuration();
            return;
        }

        const durationLabel = selectedEndOption?.dataset.durationLabel || humanizeDuration(startTimeSelect.value, endTimeSelect.value);
        const rangeLabel = selectedEndOption?.dataset.rangeLabel || `${startTimeSelect.value} to ${endTimeSelect.value}`;
        durationDisplay.value = `${durationLabel} booking window | ${rangeLabel}`;
    };

    const fetchQuote = async () => {
        const roomId = roomSelect.value;
        const bookingDate = bookingDateInput.value;
        const startTime = startTimeSelect.value;
        const endTime = endTimeSelect.value;

        if (!roomId || !bookingDate || !startTime || !endTime) {
            resetQuote();
            return;
        }

        try {
            const response = await fetch(`${quoteUrl}?hyve_room_id=${encodeURIComponent(roomId)}&booking_date=${encodeURIComponent(bookingDate)}&start_time=${encodeURIComponent(startTime)}&end_time=${encodeURIComponent(endTime)}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to load the booking quote.');
            }

            const data = await response.json();
            currentQuote = data;
            quoteTotal.textContent = formatCurrency(data.total_amount);
            quoteMinimumDownpayment.textContent = formatCurrency(data.minimum_downpayment_amount);
            downpaymentInput.min = String(data.minimum_downpayment_amount);
            if (!downpaymentInput.value || Number(downpaymentInput.value) < Number(data.minimum_downpayment_amount)) {
                downpaymentInput.value = String(data.minimum_downpayment_amount);
            }
            updateBalanceFromInput();
            quoteMeta.textContent = `${data.rate_name} | ${data.charge_period_label} | ${data.duration_hours} scheduled hour(s) | ${data.billed_hours} billed hour(s).`;
            paymentInstructions.textContent = data.payment?.instructions || 'Send the downpayment first, then upload your payment proof for verification.';
        } catch (error) {
            resetQuote('Unable to load the payment quote right now. Please keep your booking details selected and try again.');
        }
    };

    const renderChips = (container, slots, tone) => {
        container.innerHTML = '';

        if (!slots.length) {
            const emptyState = document.createElement('span');
            emptyState.className = 'rounded-full border border-white/12 px-3 py-2 text-xs uppercase tracking-[0.16em] text-white/72';
            emptyState.textContent = tone === 'available' ? 'No open windows' : 'No blocked windows';
            container.append(emptyState);
            return;
        }

        slots.forEach((slot) => {
            const chip = document.createElement('span');
            chip.className = tone === 'available'
                ? 'rounded-full border border-emerald-400/25 bg-emerald-500/12 px-3 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-emerald-100'
                : 'rounded-full border border-red-400/20 bg-red-500/12 px-3 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-red-100';
            chip.textContent = slot.label;
            container.append(chip);
        });
    };

    const renderTimeline = (items) => {
        roomDetailTimeline.innerHTML = '';

        if (!items.length) {
            const emptyState = document.createElement('div');
            emptyState.className = 'rounded-[1rem] border border-white/10 px-4 py-3 text-sm text-white/72';
            emptyState.textContent = 'No booking details found for this date.';
            roomDetailTimeline.append(emptyState);
            return;
        }

        items.forEach((item) => {
            const row = document.createElement('div');
            row.className = 'flex items-center justify-between gap-3 rounded-[1rem] border border-white/10 bg-white/5 px-4 py-3';

            const label = document.createElement('p');
            label.className = 'text-sm font-medium text-white';
            label.textContent = item.label;

            const badge = document.createElement('span');
            badge.className = item.type === 'available'
                ? 'rounded-full border border-emerald-400/25 bg-emerald-500/12 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-100'
                : 'rounded-full border border-red-400/20 bg-red-500/12 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-red-100';
            badge.textContent = item.type === 'available' ? 'Available' : 'Booked';

            row.append(label, badge);
            roomDetailTimeline.append(row);
        });
    };

    const statusLabel = (status) => {
        if (status === 'occupied') {
            return 'Occupied';
        }

        if (status === 'booked') {
            return 'Booked';
        }

        return 'Available';
    };

    const compactStatusLabel = (status) => {
        if (status === 'occupied') {
            return 'Full';
        }

        if (status === 'booked') {
            return 'Booked';
        }

        return 'Open';
    };

    const roomTypeLabel = (room) => {
        if (room.room_name === 'Conference Room') {
            return 'Conference Room';
        }

        if (room.room_name.startsWith('Room ')) {
            return 'Private Room';
        }

        return 'Shared Table Seat';
    };

    const statusClasses = {
        available: ['border-emerald-400/25', 'bg-emerald-500/10', 'text-emerald-700'],
        booked: ['border-amber-400/25', 'bg-amber-500/10', 'text-amber-700'],
        occupied: ['border-red-400/25', 'bg-red-500/10', 'text-red-700'],
    };

    const updateRoomButton = (button, room) => {
        button.classList.remove(
            'border-emerald-400/25', 'bg-emerald-500/10',
            'border-amber-400/25', 'bg-amber-500/10',
            'border-red-400/25', 'bg-red-500/10',
        );

        const stateLabel = button.querySelector('[data-layout-room-state]');
        const isSharedSeat = room.room_name.includes('-');
        stateLabel.textContent = isSharedSeat ? compactStatusLabel(room.status) : statusLabel(room.status);

        (statusClasses[room.status] || statusClasses.available).forEach((className) => {
            button.classList.add(className);
        });
    };

    const updateRoomMeta = () => {
        const selectedOption = roomSelect.options[roomSelect.selectedIndex];

        if (!selectedOption || !roomSelect.value) {
            roomMeta.textContent = 'Choose the exact room, conference room, or shared table seat that you want to book.';
            return;
        }

        const room = roomMap.get(String(roomSelect.value));
        roomMeta.textContent = room
            ? `${room.description} | ${room.space_label}`
            : selectedOption.textContent;
    };

    const renderModal = (room) => {
        const availableSlots = room.available_slots || [];
        const bookedSlots = room.booked_slots || [];
        const bookingDetails = room.booking_details || [];

        roomDetailName.textContent = room.room_name;
        roomDetailType.textContent = roomTypeLabel(room);
        roomDetailMeta.textContent = `${room.description} | ${room.space_label}`;
        roomDetailStatus.textContent = `${statusLabel(room.status)} on ${formatDateLabel(layoutDateInput.value) || 'selected date'}`;
        roomDetailNextSlot.textContent = availableSlots.length ? availableSlots[0].label : 'No open windows left';
        roomDetailAvailableCount.textContent = `${availableSlots.length} ${availableSlots.length === 1 ? 'open window' : 'open windows'}`;
        roomDetailBookedCount.textContent = `${bookedSlots.length} ${bookedSlots.length === 1 ? 'reserved window' : 'reserved windows'}`;
        renderChips(roomDetailAvailable, availableSlots, 'available');
        renderChips(roomDetailBooked, bookedSlots, 'booked');
        renderTimeline(bookingDetails);
    };

    const openModal = () => {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('aria-hidden', 'false');
        syncBodyScrollLock();
    };

    const closeModal = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.setAttribute('aria-hidden', 'true');
        syncBodyScrollLock();
    };

    const openBookingModal = () => {
        if (!bookingDateInput.value && layoutDateInput.value) {
            bookingDateInput.value = layoutDateInput.value;
        }

        bookingModal.classList.remove('hidden');
        bookingModal.classList.add('flex');
        bookingModal.setAttribute('aria-hidden', 'false');
        syncBodyScrollLock();

        window.setTimeout(() => {
            const firstField = form.querySelector('input, select, textarea');

            if (firstField) {
                firstField.focus();
            }
        }, 50);
    };

    const closeBookingModal = () => {
        bookingModal.classList.add('hidden');
        bookingModal.classList.remove('flex');
        bookingModal.setAttribute('aria-hidden', 'true');
        syncBodyScrollLock();
    };

    const renderUnavailableDates = (dates) => {
        unavailableDatesList.innerHTML = '';
        unavailableDates = new Set(dates.map((date) => date.value));

        if (!roomSelect.value) {
            unavailableDatesMessage.textContent = `Select a room first to load fully booked dates for the next ${unavailableDatesHorizon} days.`;
            return;
        }

        if (!dates.length) {
            unavailableDatesMessage.textContent = `No fully booked dates found for this room in the next ${unavailableDatesHorizon} days.`;
            return;
        }

        unavailableDatesMessage.textContent = `These dates are already fully booked for the next ${unavailableDatesHorizon} days.`;

        dates.forEach((date) => {
            const badge = document.createElement('span');
            badge.className = 'rounded-full border border-red-400/25 bg-red-500/10 px-3 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-red-700';
            badge.textContent = date.label;
            unavailableDatesList.append(badge);
        });
    };

    const fetchUnavailableDates = async () => {
        if (!roomSelect.value) {
            unavailableDates = new Set();
            unavailableDatesList.innerHTML = '';
            unavailableDatesMessage.textContent = `Select a room first to load fully booked dates for the next ${unavailableDatesHorizon} days.`;
            bookingDateInput.setCustomValidity('');
            return;
        }

        unavailableDatesMessage.textContent = 'Loading fully booked dates for this room...';
        unavailableDatesList.innerHTML = '';

        try {
            const response = await fetch(`${unavailableDatesUrl}?hyve_room_id=${encodeURIComponent(roomSelect.value)}&horizon_days=${encodeURIComponent(unavailableDatesHorizon)}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to load unavailable dates.');
            }

            const data = await response.json();
            renderUnavailableDates(Array.isArray(data.unavailable_dates) ? data.unavailable_dates : []);
        } catch (error) {
            unavailableDates = new Set();
            unavailableDatesList.innerHTML = '';
            unavailableDatesMessage.textContent = 'Unable to load fully booked dates right now. You may still continue by checking a specific date below.';
        }
    };

    const populateStartTimes = (startTimes) => {
        startTimeSelect.innerHTML = '';

        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = `Select a start time${startTimes.length ? ` (${startTimes.length} open)` : ''}`;
        startTimeSelect.append(defaultOption);

        const previousValue = startTimeSelect.dataset.previousValue || startTimeSelect.getAttribute('data-old-value') || '';

        startTimes.forEach((slot) => {
            const option = document.createElement('option');
            option.value = slot.value;
            option.textContent = slot.label;

            if (previousValue && previousValue === slot.value) {
                option.selected = true;
            }

            startTimeSelect.append(option);
        });

        startTimeSelect.disabled = false;
    };

    const populateEndTimes = (endTimes) => {
        endTimeSelect.innerHTML = '';

        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = `Select an end time${endTimes.length ? ` (${endTimes.length} open)` : ''}`;
        endTimeSelect.append(defaultOption);

        const previousValue = endTimeSelect.dataset.previousValue || endTimeSelect.getAttribute('data-old-value') || '';

        endTimes.forEach((slot) => {
            const option = document.createElement('option');
            option.value = slot.value;
            option.textContent = `${slot.label} | ${slot.duration_label}`;
            option.dataset.rangeLabel = slot.range_label;
            option.dataset.durationLabel = slot.duration_label;

            if (previousValue && previousValue === slot.value) {
                option.selected = true;
            }

            endTimeSelect.append(option);
        });

        endTimeSelect.disabled = false;
        updateDurationDisplay();
    };

    const fetchStartTimes = async () => {
        const roomId = roomSelect.value;
        const bookingDate = bookingDateInput.value;
        const room = roomMap.get(String(roomId));
        const readableDate = formatDateLabel(bookingDate);

        if (!roomId || !bookingDate) {
            resetStartTimes('Select a room and date first');
            resetEndTimes('Select a start time first');
            resetDuration();
            resetQuote();
            setLoading(false);
            bookingDateInput.setCustomValidity('');
            setMessage('Select a room and date first. Available start and end times will appear here.');
            return;
        }

        if (unavailableDates.has(bookingDate)) {
            resetStartTimes('Fully booked for this date');
            resetEndTimes('Select another date first');
            resetDuration();
            resetQuote();
            setLoading(false);
            bookingDateInput.setCustomValidity('This date is already fully booked for the selected room.');
            setMessage(`${room?.room_name ?? 'This room'} is already fully booked${readableDate ? ` on ${readableDate}` : ''}. Please choose another date or another room.`, 'error');
            return;
        }

        bookingDateInput.setCustomValidity('');
        setLoading(true);
        setMessage(`Checking available start times for ${room?.room_name ?? 'selected room'}${readableDate ? ` on ${readableDate}` : ''}...`);

        try {
            const response = await fetch(`${availabilityUrl}?hyve_room_id=${encodeURIComponent(roomId)}&booking_date=${encodeURIComponent(bookingDate)}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to load available start times.');
            }

            const data = await response.json();
            const startTimes = Array.isArray(data.start_times) ? data.start_times : [];

            if (!startTimes.length) {
                resetStartTimes('Fully booked for this date');
                resetEndTimes('Select another date first');
                resetDuration();
                resetQuote();
                setLoading(false);
                setMessage(`${room?.room_name ?? 'This room'} is fully booked${readableDate ? ` on ${readableDate}` : ''}. Please choose another date or another room.`, 'error');
                return;
            }

            populateStartTimes(startTimes);
            resetEndTimes('Select a start time first');
            resetDuration();
            resetQuote();
            setLoading(false);
            setMessage(`${startTimes.length} available ${startTimes.length === 1 ? 'start time is' : 'start times are'} ready for ${room?.room_name ?? 'selected room'}${readableDate ? ` on ${readableDate}` : ''}. Choose when you want your booking to begin.`, 'success');

            if (startTimeSelect.value) {
                await fetchEndTimes();
            }
        } catch (error) {
            resetStartTimes('Unable to load start times');
            resetEndTimes('Unable to load end times');
            resetDuration();
            resetQuote();
            setLoading(false);
            setMessage('Unable to check availability right now. Please try again.', 'error');
        }
    };

    const fetchEndTimes = async () => {
        const roomId = roomSelect.value;
        const bookingDate = bookingDateInput.value;
        const startTime = startTimeSelect.value;
        const room = roomMap.get(String(roomId));
        const readableDate = formatDateLabel(bookingDate);

        if (!roomId || !bookingDate || !startTime) {
            resetEndTimes('Select a start time first');
            resetDuration();
            resetQuote();
            return;
        }

        setLoading(true);
        setMessage(`Checking available end times for ${room?.room_name ?? 'selected room'} starting at ${startTimeSelect.options[startTimeSelect.selectedIndex]?.textContent ?? startTime}${readableDate ? ` on ${readableDate}` : ''}...`);

        try {
            const response = await fetch(`${availabilityUrl}?hyve_room_id=${encodeURIComponent(roomId)}&booking_date=${encodeURIComponent(bookingDate)}&start_time=${encodeURIComponent(startTime)}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to load available end times.');
            }

            const data = await response.json();
            const endTimes = Array.isArray(data.end_times) ? data.end_times : [];

            if (!endTimes.length) {
                resetEndTimes('No end times available');
                resetDuration();
                resetQuote();
                setLoading(false);
                setMessage(`No valid booking duration is available for this start time. Please choose another start time for ${room?.room_name ?? 'this room'}.`, 'error');
                return;
            }

            populateEndTimes(endTimes);
            await fetchQuote();
            setLoading(false);
            setMessage(`Choose how long you want to stay. ${endTimes.length} possible end ${endTimes.length === 1 ? 'time is' : 'times are'} open for this start.`, 'success');
        } catch (error) {
            resetEndTimes('Unable to load end times');
            resetDuration();
            resetQuote();
            setLoading(false);
            setMessage('Unable to load end time options right now. Please try again.', 'error');
        }
    };

    const fetchLayout = async () => {
        const bookingDate = layoutDateInput.value;

        if (!bookingDate) {
            return;
        }

        try {
            const response = await fetch(`${layoutUrl}?booking_date=${encodeURIComponent(bookingDate)}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to load room layout.');
            }

            const data = await response.json();
            const rooms = Array.isArray(data.rooms) ? data.rooms : [];
            roomMap = new Map(rooms.map((room) => [String(room.id), room]));

            roomButtons.forEach((button) => {
                const room = roomMap.get(button.dataset.roomId);

                if (room) {
                    updateRoomButton(button, room);
                }
            });

            updateRoomMeta();
        } catch (error) {
            setMessage('Unable to load the room layout right now. Please try again.', 'error');
        }
    };

    roomButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const room = roomMap.get(button.dataset.roomId);

            if (!room) {
                return;
            }

            renderModal(room);
            openModal();
        });
    });

    bookingOpenButtons.forEach((button) => {
        button.addEventListener('click', () => {
            openBookingModal();
        });
    });

    bookingClose.addEventListener('click', closeBookingModal);
    bookingModal.addEventListener('click', (event) => {
        if (event.target === bookingModal) {
            closeBookingModal();
        }
    });

    modalClose.addEventListener('click', closeModal);
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && bookingModal.getAttribute('aria-hidden') === 'false') {
            closeBookingModal();
            return;
        }

        if (event.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') {
            closeModal();
        }
    });

    roomSelect.addEventListener('change', async () => {
        updateRoomMeta();
        await fetchUnavailableDates();
        resetEndTimes('Select a start time first');
        resetDuration();
        resetQuote();
        await fetchStartTimes();
    });

    bookingDateInput.addEventListener('change', async () => {
        await fetchUnavailableDates();
        resetEndTimes('Select a start time first');
        resetDuration();
        resetQuote();
        await fetchStartTimes();
    });

    layoutDateInput.addEventListener('change', fetchLayout);

    startTimeSelect.addEventListener('change', async () => {
        startTimeSelect.dataset.previousValue = startTimeSelect.value;
        endTimeSelect.dataset.previousValue = '';
        resetDuration();
        resetQuote();
        await fetchEndTimes();
    });

    endTimeSelect.addEventListener('change', async () => {
        endTimeSelect.dataset.previousValue = endTimeSelect.value;
        updateDurationDisplay();
        await fetchQuote();

        if (startTimeSelect.value && endTimeSelect.value) {
            const durationLabel = endTimeSelect.options[endTimeSelect.selectedIndex]?.dataset.durationLabel || humanizeDuration(startTimeSelect.value, endTimeSelect.value);
            setMessage(`Your selected booking window is ${durationLabel}. Review your details, then submit when ready.`, 'success');
        }
    });

    paymentMethodSelect.addEventListener('change', updatePaymentDestination);
    downpaymentInput.addEventListener('input', updateBalanceFromInput);

    startTimeSelect.dataset.previousValue = startTimeSelect.value;
    endTimeSelect.dataset.previousValue = endTimeSelect.value;
    fetchLayout();
    updateRoomMeta();
    updatePaymentDestination();
    syncBodyScrollLock();

    if (roomSelect.value) {
        fetchUnavailableDates().then(fetchStartTimes);
    } else {
        resetStartTimes('Select a room and date first');
        resetEndTimes('Select a start time first');
        resetDuration();
        resetQuote();
        setMessage('Select a room and date first. Available start and end times will appear here.');
    }

    if (bookingModal.dataset.openOnLoad === 'true') {
        openBookingModal();
    }
};

document.addEventListener('DOMContentLoaded', () => {
    setupNavState();
    setupMobileMenu();
    setupSmoothScroll();
    setupActiveSection();
    setupReveal();
    setupRatePanels();
    setupBookingAvailability();
});
