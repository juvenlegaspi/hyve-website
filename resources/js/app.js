const setupNav = () => {
    const shell = document.querySelector('[data-home-shell]');
    const nav = document.getElementById('site-nav');
    const toggle = document.getElementById('menu-toggle');
    const menu = document.getElementById('mobile-menu');
    const links = [...document.querySelectorAll('.nav-link[href^="#"]')];
    const sections = [...document.querySelectorAll('section[id]')];
    const homeTriggers = [...document.querySelectorAll('[data-nav-mode="home"]')];
    const spacesTriggers = [...document.querySelectorAll('[data-nav-mode="spaces"]')];
    const anchorTriggers = [...document.querySelectorAll('[data-nav-anchor]')];

    const setPageMode = (mode) => {
        if (!shell) {
            return;
        }

        shell.setAttribute('data-page-mode', mode);
    };

    const closeMenu = () => {
        if (!toggle || !menu) {
            return;
        }

        menu.classList.add('hidden');
        toggle.setAttribute('aria-expanded', 'false');
    };

    const scrollToTarget = (selector) => {
        const target = document.querySelector(selector);

        if (!target) {
            return;
        }

        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    if (nav) {
        const onScroll = () => {
            nav.classList.toggle('site-nav--scrolled', window.scrollY > 24);
        };

        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    homeTriggers.forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            setPageMode('home');
            closeMenu();
            window.setTimeout(() => scrollToTarget('#overview'), 30);
        });
    });

    spacesTriggers.forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            setPageMode('spaces');
            closeMenu();
            window.setTimeout(() => scrollToTarget('#spaces-view'), 30);
        });
    });

    anchorTriggers.forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const href = trigger.getAttribute('href') || '';

            if (href !== '#why-hyve' && href !== '#contact') {
                setPageMode('home');
            }

            closeMenu();
        });
    });

    if (links.length && sections.length) {
        const setActive = (id) => {
            links.forEach((link) => {
                link.classList.toggle('is-active', link.getAttribute('href') === `#${id}`);
            });
        };

        const observer = new IntersectionObserver((entries) => {
            const visible = entries
                .filter((entry) => entry.isIntersecting)
                .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];

            if (visible?.target?.id) {
                setActive(visible.target.id);
            }
        }, {
            rootMargin: '-35% 0px -45% 0px',
            threshold: [0.2, 0.45, 0.7],
        });

        sections.forEach((section) => observer.observe(section));
    }

    if (!toggle || !menu) {
        return;
    }

    toggle.addEventListener('click', () => {
        const isOpen = !menu.classList.contains('hidden');
        menu.classList.toggle('hidden', isOpen);
        toggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
    });

    menu.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', closeMenu);
    });
};

const setupSpacesBrowser = () => {
    const filter = document.querySelector('[data-spaces-filter]');
    const cards = [...document.querySelectorAll('[data-space-card]')];
    const count = document.querySelector('[data-space-count]');

    if (!filter || !cards.length || !count) {
        return;
    }

    const search = filter.querySelector('[data-space-search]');
    const category = filter.querySelector('[data-space-category]');
    const capacity = filter.querySelector('[data-space-capacity]');

    if (!search || !category || !capacity) {
        return;
    }

    const matchesCapacity = (cardCapacity, selected) => {
        if (!selected) {
            return true;
        }

        const numeric = Number(cardCapacity);

        if (selected === '1-2') {
            return numeric >= 1 && numeric <= 2;
        }

        if (selected === '3-4') {
            return numeric >= 3 && numeric <= 4;
        }

        if (selected === '5+') {
            return numeric >= 5;
        }

        return true;
    };

    const applyFilters = () => {
        const searchValue = search.value.trim().toLowerCase();
        const categoryValue = category.value.trim().toLowerCase();
        const capacityValue = capacity.value;
        let visibleCount = 0;

        cards.forEach((card) => {
            const title = card.getAttribute('data-space-title') || '';
            const cardCategory = card.getAttribute('data-space-category') || '';
            const cardCapacity = card.getAttribute('data-space-capacity') || '';

            const visible = (!searchValue || title.includes(searchValue))
                && (!categoryValue || cardCategory === categoryValue)
                && matchesCapacity(cardCapacity, capacityValue);

            card.classList.toggle('hidden', !visible);

            if (visible) {
                visibleCount += 1;
            }
        });

        count.textContent = String(visibleCount);
    };

    filter.addEventListener('submit', (event) => {
        event.preventDefault();
        applyFilters();
    });

    [search, category, capacity].forEach((field) => {
        field.addEventListener('input', applyFilters);
        field.addEventListener('change', applyFilters);
    });
};

const setupReveal = () => {
    const items = document.querySelectorAll('.reveal');

    if (!items.length) {
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }

            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.15 });

    items.forEach((item) => observer.observe(item));
};

const setupBookingPage = () => {
    const page = document.querySelector('[data-booking-page]');
    const form = document.querySelector('[data-booking-form]');

    if (!page || !form) {
        return;
    }

    const availabilityUrl = form.dataset.availabilityUrl;
    const unavailableDatesUrl = form.dataset.unavailableDatesUrl;
    const quoteUrl = form.dataset.quoteUrl;
    const minimumDuration = Number(form.dataset.minimumDuration || '60');
    const horizonDays = Number(form.dataset.unavailableDatesHorizon || '30');

    const roomSelect = form.querySelector('[data-room-select]');
    const bookingDateInput = form.querySelector('[data-booking-date]');
    const startSelect = form.querySelector('[data-start-time-select]');
    const endSelect = form.querySelector('[data-end-time-select]');
    const bookingPicker = form.querySelector('[data-booking-picker]');
    const bookingCheckout = form.querySelector('[data-booking-checkout]');
    const checkoutBack = form.querySelector('[data-checkout-back]');
    const durationDisplay = form.querySelector('[data-duration-display]');
    const downpaymentInput = form.querySelector('[data-downpayment-input]');
    const paymentMethod = form.querySelector('[data-payment-method]');
    const paymentMethodCards = [...form.querySelectorAll('[data-payment-choice]')];
    const roomMeta = form.querySelector('[data-selected-room-meta]');
    const messageBody = form.querySelector('[data-availability-message-body]');
    const quoteTotal = form.querySelector('[data-quote-total]');
    const quoteMinimum = form.querySelector('[data-quote-minimum-downpayment]');
    const quoteBalance = form.querySelector('[data-quote-balance]');
    const quoteMeta = form.querySelector('[data-quote-meta]');
    const paymentGcash = form.querySelector('[data-payment-gcash]');
    const paymentBank = form.querySelector('[data-payment-bank]');
    const paymentInstructions = form.querySelector('[data-payment-instructions]');
    const roomCards = [...form.querySelectorAll('[data-room-card]')];
    const roomRail = form.querySelector('[data-room-cards]');
    const roomScrollPrev = form.querySelector('[data-room-scroll-prev]');
    const roomScrollNext = form.querySelector('[data-room-scroll-next]');
    const calendarTitle = form.querySelector('[data-calendar-title]');
    const calendarDays = form.querySelector('[data-calendar-days]');
    const calendarPrev = form.querySelector('[data-calendar-prev]');
    const calendarNext = form.querySelector('[data-calendar-next]');
    const slotDateTitle = form.querySelector('[data-slot-date-title]');
    const slotContinue = form.querySelector('[data-slot-continue]');
    const selectedRoomName = form.querySelector('[data-selected-room-name]');
    const selectedRoomSpace = form.querySelector('[data-selected-room-space]');
    const selectedRoomRate = form.querySelector('[data-selected-room-rate]');
    const startSlots = form.querySelector('[data-start-slots]');
    const endSlots = form.querySelector('[data-end-slots]');
    const startStep = form.querySelector('[data-start-step]');
    const startSummary = form.querySelector('[data-start-summary]');
    const startSummaryTime = form.querySelector('[data-start-summary-time]');
    const startSummaryChange = form.querySelector('[data-start-summary-change]');
    const inlineSummary = form.querySelector('[data-inline-summary]');
    const summaryDate = form.querySelector('[data-summary-date]');
    const summaryStart = form.querySelector('[data-summary-start]');
    const summaryEnd = form.querySelector('[data-summary-end]');
    const summaryDuration = form.querySelector('[data-summary-duration]');
    const summaryRate = form.querySelector('[data-summary-rate]');
    const summaryTotal = form.querySelector('[data-summary-total]');
    const checkoutRoom = form.querySelector('[data-checkout-room]');
    const checkoutDate = form.querySelector('[data-checkout-date]');
    const checkoutStart = form.querySelector('[data-checkout-start]');
    const checkoutEnd = form.querySelector('[data-checkout-end]');
    const checkoutDuration = form.querySelector('[data-checkout-duration]');
    const checkoutStandardSummary = form.querySelector('[data-checkout-standard-summary]');
    const checkoutScheduleCount = form.querySelector('[data-checkout-schedule-count]');
    const checkoutScheduleList = form.querySelector('[data-checkout-schedule-list]');
    const checkoutSubmit = form.querySelector('[data-checkout-submit]');
    const shouldShowCheckout = form.dataset.showCheckout === 'true';
    const initialStartTime = startSelect.value;
    const initialEndTime = endSelect.value;

    if (!roomSelect || !bookingDateInput || !startSelect || !endSelect || !bookingPicker || !bookingCheckout || !checkoutBack || !durationDisplay || !downpaymentInput || !paymentMethod || !paymentMethodCards.length || !roomMeta || !messageBody || !quoteTotal || !quoteMinimum || !quoteBalance || !quoteMeta || !quoteMinimum || !paymentGcash || !paymentBank || !paymentInstructions || !roomCards.length || !roomRail || !calendarTitle || !calendarDays || !calendarPrev || !calendarNext || !slotDateTitle || !slotContinue || !selectedRoomName || !selectedRoomSpace || !selectedRoomRate || !startSlots || !endSlots || !startStep || !startSummary || !startSummaryTime || !startSummaryChange || !inlineSummary || !summaryDate || !summaryStart || !summaryEnd || !summaryDuration || !summaryRate || !summaryTotal || !checkoutRoom || !checkoutDate || !checkoutStart || !checkoutEnd || !checkoutDuration || !checkoutSubmit) {
        return;
    }

    let blockedDates = new Set();
    let currentQuote = null;
    let currentMonth = (() => {
        const base = bookingDateInput.value ? new Date(`${bookingDateInput.value}T00:00:00`) : new Date();
        return new Date(base.getFullYear(), base.getMonth(), 1);
    })();

    const today = new Date();
    const todayValue = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
    const currentMinutes = (today.getHours() * 60) + today.getMinutes();

    const formatCurrency = (value) => `Php ${Number(value || 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;

    const formatDate = (value) => {
        if (!value) {
            return '';
        }

        const date = new Date(`${value}T00:00:00`);

        if (Number.isNaN(date.getTime())) {
            return value;
        }

        return new Intl.DateTimeFormat('en-PH', {
            month: 'long',
            day: 'numeric',
            year: 'numeric',
        }).format(date);
    };

    const formatMonthTitle = (date) => new Intl.DateTimeFormat('en-PH', {
        month: 'long',
        year: 'numeric',
    }).format(date);

    const durationLabel = (startValue, endValue) => {
        if (!startValue || !endValue) {
            return '';
        }

        const [startHour, startMinute] = startValue.split(':').map(Number);
        const [endHour, endMinute] = endValue.split(':').map(Number);

        if ([startHour, startMinute, endHour, endMinute].some(Number.isNaN)) {
            return '';
        }

        let startTotal = (startHour * 60) + startMinute;
        let endTotal = (endHour * 60) + endMinute;

        if (endTotal <= startTotal) {
            endTotal += 24 * 60;
        }

        const diff = endTotal - startTotal;
        const hours = Math.floor(diff / 60);
        const minutes = diff % 60;
        const parts = [];

        if (hours) {
            parts.push(`${hours} ${hours === 1 ? 'hour' : 'hours'}`);
        }

        if (minutes) {
            parts.push(`${minutes} mins`);
        }

        return parts.join(' ');
    };

    const timeValueToMinutes = (value) => {
        if (!value || !value.includes(':')) {
            return null;
        }

        const [hour, minute] = value.split(':').map(Number);

        if ([hour, minute].some(Number.isNaN)) {
            return null;
        }

        return (hour * 60) + minute;
    };

    const currentScheduleCutoffMinutes = () => {
        const now = new Date();
        const minutes = (now.getHours() * 60) + now.getMinutes();

        if (minutes % 30 === 0) {
            return minutes;
        }

        return minutes + (30 - (minutes % 30));
    };

    const minutesToTimeValue = (minutes) => {
        const normalized = ((minutes % (24 * 60)) + (24 * 60)) % (24 * 60);
        const hour = Math.floor(normalized / 60);
        const minute = normalized % 60;

        return `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
    };

    const filterPastStartTimes = (items) => {
        if (bookingDateInput.value !== todayValue) {
            return items;
        }

        return items.filter((item) => {
            const minutes = timeValueToMinutes(item.value);

            if (minutes === null) {
                return true;
            }

            return minutes >= currentMinutes;
        });
    };

    const updatePaymentDestination = () => {
        paymentGcash.classList.toggle('hidden', paymentMethod.value !== 'gcash');
        paymentBank.classList.toggle('hidden', paymentMethod.value !== 'bank_transfer');
        paymentMethodCards.forEach((card) => {
            card.classList.toggle('is-active', card.dataset.paymentChoice === paymentMethod.value);
        });
    };

    const showCheckout = () => {
        bookingPicker.classList.add('hidden');
        bookingCheckout.classList.remove('hidden');
    };

    const showPicker = () => {
        bookingCheckout.classList.add('hidden');
        bookingPicker.classList.remove('hidden');
    };

    const updateCheckoutSummary = () => {
        const roomCard = getSelectedRoomCard();
        checkoutRoom.textContent = roomCard
            ? `${roomCard.dataset.roomName || 'Choose a room'} · ${roomCard.dataset.roomSpace || ''}`.trim()
            : 'Choose a room';
        checkoutDate.textContent = formatDate(bookingDateInput.value || todayValue);
        checkoutStart.textContent = startSelect.selectedOptions[0]?.textContent ?? startSelect.value ?? '--:--';
        checkoutEnd.textContent = endSelect.selectedOptions[0]?.textContent ?? endSelect.value ?? '--:--';
        checkoutDuration.textContent = durationLabel(startSelect.value, endSelect.value) || '--';
    };

    const updateBalance = () => {
        if (!currentQuote) {
            quoteBalance.textContent = 'Php 0.00';
            checkoutSubmit.textContent = 'Confirm & Pay Php 0.00';
            return;
        }

        const total = Number(currentQuote.total_amount || 0);
        const minimum = Number(currentQuote.minimum_downpayment_amount || 0);
        let current = Number(downpaymentInput.value || 0);

        if (Number.isNaN(current) || current < minimum) {
            current = minimum;
        }

        if (current > total) {
            current = total;
        }

        quoteBalance.textContent = formatCurrency(total - current);
        checkoutSubmit.textContent = `Confirm & Pay ${formatCurrency(current)}`;
    };

    const hideInlineSummary = () => {
        inlineSummary.classList.add('hidden');
        summaryDate.textContent = formatDate(bookingDateInput.value || todayValue);
        summaryStart.textContent = '--:--';
        summaryEnd.textContent = '--:--';
        summaryDuration.textContent = '--';
        summaryRate.textContent = '--';
        summaryTotal.textContent = 'Php 0.00';
    };

    const hideStartSummary = () => {
        startSummary.classList.add('hidden');
        startSummaryTime.textContent = '--:--';
        startStep.classList.remove('hidden');
    };

    const showStartSummary = () => {
        const startLabel = startSelect.selectedOptions[0]?.textContent ?? startSelect.value ?? '--:--';
        startSummaryTime.textContent = startLabel;
        startSummary.classList.remove('hidden');
        startStep.classList.add('hidden');
    };

    const resetQuote = () => {
        currentQuote = null;
        quoteTotal.textContent = 'Php 0.00';
        quoteMinimum.textContent = 'Php 0.00';
        quoteBalance.textContent = 'Php 0.00';
        quoteMeta.textContent = 'Choose a room, date, start time, and end time first to load your live rate summary.';
        hideInlineSummary();
        slotContinue.textContent = 'Pick a time to continue';
        slotContinue.disabled = true;
        checkoutSubmit.textContent = 'Confirm & Pay Php 0.00';
    };

    const resetSlots = (message = 'Select a room and date first. Available times will appear here.') => {
        scheduleRangeStart = null;
        startSelect.innerHTML = '';
        endSelect.innerHTML = '';
        startSlots.innerHTML = '<span class="booking-slot-empty">No start times loaded yet.</span>';
        endSlots.innerHTML = '<span class="booking-slot-empty">Select a start time first.</span>';
        durationDisplay.textContent = 'Choose a start time first to continue.';
        messageBody.textContent = message;
        hideStartSummary();
        hideInlineSummary();
        updateCheckoutSummary();
    };

    const getSelectedRoomCard = () => roomCards.find((card) => card.dataset.roomId === roomSelect.value);

    const updateRoomMeta = () => {
        const roomCard = getSelectedRoomCard();

        if (!roomCard) {
            roomMeta.textContent = 'Choose the exact room first, then pick an available date and start time.';
            selectedRoomName.textContent = 'Choose a room';
            selectedRoomSpace.textContent = '';
            selectedRoomRate.textContent = 'Ask HYVE';
            checkoutRoom.textContent = 'Choose a room';
            return;
        }

        roomMeta.textContent = `${roomCard.dataset.roomDescription} | ${roomCard.dataset.roomSpace}`;
        selectedRoomName.textContent = roomCard.dataset.roomName || 'Choose a room';
        selectedRoomSpace.textContent = roomCard.dataset.roomSpace || '';
        selectedRoomRate.textContent = roomCard.dataset.roomRate || 'Ask HYVE';
        checkoutRoom.textContent = `${roomCard.dataset.roomName || 'Choose a room'} · ${roomCard.dataset.roomSpace || ''}`.trim();
    };

    const updateSlotHeading = () => {
        slotDateTitle.textContent = formatDate(bookingDateInput.value || todayValue);
        checkoutDate.textContent = formatDate(bookingDateInput.value || todayValue);
    };

    const setActiveRoom = (roomId) => {
        roomSelect.value = roomId;
        roomCards.forEach((card) => {
            card.classList.toggle('is-active', card.dataset.roomId === roomId);
        });
        updateRoomMeta();
    };

    const renderCalendar = () => {
        calendarTitle.textContent = formatMonthTitle(currentMonth);
        calendarDays.innerHTML = '';

        const firstDay = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), 1);
        const lastDay = new Date(currentMonth.getFullYear(), currentMonth.getMonth() + 1, 0);
        const leading = firstDay.getDay();

        for (let i = 0; i < leading; i += 1) {
            const filler = document.createElement('span');
            filler.className = 'booking-calendar-day is-filler';
            calendarDays.append(filler);
        }

        for (let day = 1; day <= lastDay.getDate(); day += 1) {
            const date = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), day);
            const value = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'booking-calendar-day';
            button.textContent = String(day);

            const isPast = value < todayValue;
            const isBooked = blockedDates.has(value);
            const isSelected = bookingDateInput.value === value;
            const isToday = value === todayValue;

            if (isToday) {
                button.classList.add('is-today');
            }

            if (isSelected) {
                button.classList.add('is-selected');
            }

            if (isBooked) {
                button.classList.add('is-booked');
            }

            if (isPast || isBooked) {
                button.disabled = true;
            }

            button.addEventListener('click', async () => {
                bookingDateInput.value = value;
                updateSlotHeading();
                renderCalendar();

                try {
                    await fetchStartTimes();
                } catch (error) {
                    messageBody.textContent = 'Unable to load date availability right now. Please try again.';
                }
            });

            calendarDays.append(button);
        }
    };

    const renderSlotButtons = (container, items, type) => {
        container.innerHTML = '';

        if (!items.length) {
            const empty = document.createElement('span');
            empty.className = 'booking-slot-empty';
            empty.textContent = type === 'start' ? 'No available start times for this date.' : 'Select a start time first.';
            container.append(empty);
            return;
        }

        items.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'booking-slot-pill';
            button.innerHTML = `<strong>${item.label}</strong>${item.duration_label ? `<small>${item.duration_label}</small>` : ''}`;

            if ((type === 'start' && startSelect.value === item.value) || (type === 'end' && endSelect.value === item.value)) {
                button.classList.add('is-active');
            }

            button.addEventListener('click', async () => {
                if (type === 'start') {
                    startSelect.value = item.value;
                    endSelect.value = '';
                    showStartSummary();
                    hideInlineSummary();

                    try {
                        await fetchEndTimes();
                    } catch (error) {
                        messageBody.textContent = 'Unable to load end time options right now. Please try again.';
                    }

                    return;
                }

                endSelect.value = item.value;
                durationDisplay.textContent = `${item.duration_label} | ${item.range_label}`;

                try {
                    await fetchQuote();
                    renderSlotButtons(container, items, 'end');
                } catch (error) {
                    messageBody.textContent = 'Unable to load the booking quote right now. Please try again.';
                }
            });

            container.append(button);
        });
    };

    const fetchJson = async (url) => {
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('Request failed.');
        }

        return response.json();
    };

    const fetchUnavailableDates = async () => {
        if (!roomSelect.value) {
            blockedDates = new Set();
            renderCalendar();
            return;
        }

        const data = await fetchJson(`${unavailableDatesUrl}?hyve_room_id=${encodeURIComponent(roomSelect.value)}&horizon_days=${encodeURIComponent(horizonDays)}`);
        blockedDates = new Set((Array.isArray(data.unavailable_dates) ? data.unavailable_dates : []).map((item) => item.value));
        renderCalendar();
    };

    const setHiddenOptions = (select, items) => {
        select.innerHTML = '';

        items.forEach((item) => {
            const option = document.createElement('option');
            option.value = item.value;
            option.textContent = item.label;

            if (item.range_label) {
                option.dataset.rangeLabel = item.range_label;
            }

            if (item.duration_label) {
                option.dataset.durationLabel = item.duration_label;
            }

            select.append(option);
        });
    };

    const fetchStartTimes = async () => {
        if (!roomSelect.value || !bookingDateInput.value) {
            resetSlots();
            resetQuote();
            return;
        }

        if (blockedDates.has(bookingDateInput.value)) {
            resetSlots('This room is fully booked for the selected date. Please choose another day.');
            resetQuote();
            return;
        }

        const data = await fetchJson(`${availabilityUrl}?hyve_room_id=${encodeURIComponent(roomSelect.value)}&booking_date=${encodeURIComponent(bookingDateInput.value)}`);
        const startTimes = filterPastStartTimes(Array.isArray(data.start_times) ? data.start_times : []);

        setHiddenOptions(startSelect, startTimes);
        endSelect.innerHTML = '';
        endSlots.innerHTML = '<span class="booking-slot-empty">Select a start time first.</span>';
        durationDisplay.textContent = 'Choose a start time first to continue.';
        hideStartSummary();
        hideInlineSummary();
        resetQuote();
        renderSlotButtons(startSlots, startTimes, 'start');

        if (!startTimes.length) {
            messageBody.textContent = bookingDateInput.value === todayValue
                ? 'No more booking windows are available for the rest of today.'
                : 'No booking windows are available for that room on the selected date.';
            return;
        }

        messageBody.textContent = `${startTimes.length} start ${startTimes.length === 1 ? 'time is' : 'times are'} available on ${formatDate(bookingDateInput.value)}.`;
    };

    const fetchEndTimes = async () => {
        if (!roomSelect.value || !bookingDateInput.value || !startSelect.value) {
            endSelect.innerHTML = '';
            endSlots.innerHTML = '<span class="booking-slot-empty">Select a start time first.</span>';
            durationDisplay.textContent = 'Choose a start time first to continue.';
            resetQuote();
            return;
        }

        const data = await fetchJson(`${availabilityUrl}?hyve_room_id=${encodeURIComponent(roomSelect.value)}&booking_date=${encodeURIComponent(bookingDateInput.value)}&start_time=${encodeURIComponent(startSelect.value)}`);
        const endTimes = Array.isArray(data.end_times) ? data.end_times : [];

        setHiddenOptions(endSelect, endTimes);
        showStartSummary();
        renderSlotButtons(
            startSlots,
            Array.from(startSelect.options).map((option) => ({ value: option.value, label: option.textContent })).filter((item) => item.value),
            'start',
        );
        renderSlotButtons(endSlots, endTimes, 'end');

        if (!endTimes.length) {
            durationDisplay.textContent = `Minimum booking is ${minimumDuration / 60} hour.`;
            resetQuote();
            messageBody.textContent = 'No valid end time is available for that start time. Please choose another start time.';
            return;
        }

        messageBody.textContent = 'Choose how long you want to stay. The available end times are ready below.';
    };

    const fetchQuote = async () => {
        if (!roomSelect.value || !bookingDateInput.value || !startSelect.value || !endSelect.value) {
            resetQuote();
            return;
        }

        const data = await fetchJson(`${quoteUrl}?hyve_room_id=${encodeURIComponent(roomSelect.value)}&booking_date=${encodeURIComponent(bookingDateInput.value)}&start_time=${encodeURIComponent(startSelect.value)}&end_time=${encodeURIComponent(endSelect.value)}`);

        currentQuote = data;
        quoteTotal.textContent = formatCurrency(data.total_amount);
        quoteMinimum.textContent = formatCurrency(data.minimum_downpayment_amount);
        quoteMeta.textContent = `${data.rate_name} | ${data.charge_period_label} | ${data.duration_hours} scheduled hour(s) | ${data.billed_hours} billed hour(s).`;
        paymentInstructions.textContent = data.payment?.instructions || paymentInstructions.textContent;

        downpaymentInput.min = String(data.minimum_downpayment_amount);

        if (!downpaymentInput.value || Number(downpaymentInput.value) < Number(data.minimum_downpayment_amount)) {
            downpaymentInput.value = String(data.minimum_downpayment_amount);
        }

        durationDisplay.textContent = endSelect.selectedOptions[0]?.dataset.durationLabel
            ? `${endSelect.selectedOptions[0].dataset.durationLabel} | ${endSelect.selectedOptions[0].dataset.rangeLabel}`
            : durationLabel(startSelect.value, endSelect.value);

        summaryDate.textContent = formatDate(bookingDateInput.value || todayValue);
        summaryStart.textContent = startSelect.selectedOptions[0]?.textContent ?? startSelect.value;
        summaryEnd.textContent = endSelect.selectedOptions[0]?.textContent ?? endSelect.value;
        summaryDuration.textContent = data.duration_hours === 1 ? '1 hour' : `${data.duration_hours} hours`;
        summaryRate.textContent = `${data.rate_name} - ${data.charge_period_label}`;
        summaryTotal.textContent = formatCurrency(data.total_amount);
        inlineSummary.classList.remove('hidden');
        checkoutStart.textContent = startSelect.selectedOptions[0]?.textContent ?? startSelect.value;
        checkoutEnd.textContent = endSelect.selectedOptions[0]?.textContent ?? endSelect.value;
        checkoutDuration.textContent = data.duration_hours === 1 ? '1 hour' : `${data.duration_hours} hours`;

        updateBalance();
        messageBody.textContent = 'Your booking window and quote are ready. Review the payment details before submitting.';
        slotContinue.textContent = 'Continue to checkout ->';
        slotContinue.disabled = false;
    };

    roomCards.forEach((card) => {
        card.addEventListener('click', async () => {
            setActiveRoom(card.dataset.roomId || '');

            try {
                await fetchUnavailableDates();
                await fetchStartTimes();
            } catch (error) {
                messageBody.textContent = 'Unable to load room availability right now. Please try again.';
            }
        });
    });

    paymentMethodCards.forEach((card) => {
        card.addEventListener('click', () => {
            paymentMethod.value = card.dataset.paymentChoice || '';
            updatePaymentDestination();
        });
    });
    paymentMethod.addEventListener('change', updatePaymentDestination);
    downpaymentInput.addEventListener('input', updateBalance);
    roomScrollPrev?.addEventListener('click', () => {
        roomRail.scrollBy({ left: -320, behavior: 'smooth' });
    });
    roomScrollNext?.addEventListener('click', () => {
        roomRail.scrollBy({ left: 320, behavior: 'smooth' });
    });
    startSummaryChange.addEventListener('click', async () => {
        endSelect.innerHTML = '';
        endSlots.innerHTML = '<span class="booking-slot-empty">Select a new start time.</span>';
        startSelect.value = '';
        durationDisplay.textContent = 'Choose a start time first to continue.';
        hideStartSummary();
        resetQuote();

        try {
            await fetchStartTimes();
        } catch (error) {
            messageBody.textContent = 'Unable to reload start times right now. Please try again.';
        }
    });
    slotContinue.addEventListener('click', () => {
        if (slotContinue.disabled) {
            return;
        }

        updateCheckoutSummary();
        showCheckout();
    });
    checkoutBack.addEventListener('click', () => {
        showPicker();
    });

    calendarPrev.addEventListener('click', () => {
        currentMonth = new Date(currentMonth.getFullYear(), currentMonth.getMonth() - 1, 1);
        renderCalendar();
    });

    calendarNext.addEventListener('click', () => {
        currentMonth = new Date(currentMonth.getFullYear(), currentMonth.getMonth() + 1, 1);
        renderCalendar();
    });

    updatePaymentDestination();
    updateSlotHeading();
    updateRoomMeta();
    updateCheckoutSummary();
    renderCalendar();
    resetSlots();
    resetQuote();

    if (roomSelect.value) {
        setActiveRoom(roomSelect.value);
        fetchUnavailableDates()
            .then(fetchStartTimes)
            .then(async () => {
                if (!initialStartTime) {
                    return;
                }

                startSelect.value = initialStartTime;
                showStartSummary();
                await fetchEndTimes();

                if (!initialEndTime) {
                    return;
                }

                endSelect.value = initialEndTime;
                await fetchQuote();
            })
            .catch(() => {
                messageBody.textContent = 'Unable to load saved booking values right now. Please reselect your details.';
            });
    }

    if (shouldShowCheckout) {
        showCheckout();
    }
};

const setupBookingPageV2 = () => {
    const page = document.querySelector('[data-booking-page]');
    const form = document.querySelector('[data-booking-form]');

    if (!page || !form) {
        return;
    }

    const availabilityUrl = form.dataset.availabilityUrl;
    const unavailableDatesUrl = form.dataset.unavailableDatesUrl;
    const quoteUrl = form.dataset.quoteUrl;
    const layoutUrl = form.dataset.layoutUrl;
    const minimumDuration = Number(form.dataset.minimumDuration || '60');
    const horizonDays = Number(form.dataset.unavailableDatesHorizon || '30');

    const modeTriggers = [...form.querySelectorAll('[data-booking-mode-trigger]')];
    const modePanels = [...form.querySelectorAll('[data-booking-mode-panel]')];
    const roomSelect = form.querySelector('[data-room-select]');
    const bookingDateInput = form.querySelector('[data-booking-date]');
    const bookingModeInput = form.querySelector('[data-booking-mode-input]');
    const scheduleItemsInput = form.querySelector('[data-schedule-items-input]');
    const startSelect = form.querySelector('[data-start-time-select]');
    const endSelect = form.querySelector('[data-end-time-select]');
    const bookingPicker = form.querySelector('[data-booking-picker]');
    const bookingCheckout = form.querySelector('[data-booking-checkout]');
    const checkoutBack = form.querySelector('[data-checkout-back]');
    const durationDisplay = form.querySelector('[data-duration-display]');
    const downpaymentInput = form.querySelector('[data-downpayment-input]');
    const paymentMethod = form.querySelector('[data-payment-method]');
    const paymentMethodCards = [...form.querySelectorAll('[data-payment-choice]')];
    const roomMeta = form.querySelector('[data-selected-room-meta]');
    const messageBody = form.querySelector('[data-availability-message-body]');
    const quoteTotal = form.querySelector('[data-quote-total]');
    const quoteMinimum = form.querySelector('[data-quote-minimum-downpayment]');
    const quoteBalance = form.querySelector('[data-quote-balance]');
    const quoteMeta = form.querySelector('[data-quote-meta]');
    const paymentGcash = form.querySelector('[data-payment-gcash]');
    const paymentBank = form.querySelector('[data-payment-bank]');
    const paymentInstructions = form.querySelector('[data-payment-instructions]');
    const roomCards = [...form.querySelectorAll('[data-room-card]')];
    const roomRail = form.querySelector('[data-room-cards]');
    const roomScrollPrev = form.querySelector('[data-room-scroll-prev]');
    const roomScrollNext = form.querySelector('[data-room-scroll-next]');
    const calendarTitle = form.querySelector('[data-calendar-title]');
    const calendarDays = form.querySelector('[data-calendar-days]');
    const calendarPrev = form.querySelector('[data-calendar-prev]');
    const calendarNext = form.querySelector('[data-calendar-next]');
    const slotDateTitle = form.querySelector('[data-slot-date-title]');
    const slotContinue = form.querySelector('[data-slot-continue]');
    const selectedRoomName = form.querySelector('[data-selected-room-name]');
    const selectedRoomSpace = form.querySelector('[data-selected-room-space]');
    const selectedRoomRate = form.querySelector('[data-selected-room-rate]');
    const startSlots = form.querySelector('[data-start-slots]');
    const endSlots = form.querySelector('[data-end-slots]');
    const startStep = form.querySelector('[data-start-step]');
    const startSummary = form.querySelector('[data-start-summary]');
    const startSummaryTime = form.querySelector('[data-start-summary-time]');
    const startSummaryChange = form.querySelector('[data-start-summary-change]');
    const inlineSummary = form.querySelector('[data-inline-summary]');
    const summaryDate = form.querySelector('[data-summary-date]');
    const summaryStart = form.querySelector('[data-summary-start]');
    const summaryEnd = form.querySelector('[data-summary-end]');
    const summaryDuration = form.querySelector('[data-summary-duration]');
    const summaryRate = form.querySelector('[data-summary-rate]');
    const summaryTotal = form.querySelector('[data-summary-total]');
    const checkoutRoom = form.querySelector('[data-checkout-room]');
    const checkoutDate = form.querySelector('[data-checkout-date]');
    const checkoutStart = form.querySelector('[data-checkout-start]');
    const checkoutEnd = form.querySelector('[data-checkout-end]');
    const checkoutDuration = form.querySelector('[data-checkout-duration]');
    const checkoutStandardSummary = form.querySelector('[data-checkout-standard-summary]');
    const checkoutScheduleCount = form.querySelector('[data-checkout-schedule-count]');
    const checkoutScheduleList = form.querySelector('[data-checkout-schedule-list]');
    const checkoutSubmit = form.querySelector('[data-checkout-submit]');
    const guestFirstName = form.querySelector('[data-guest-first-name]');
    const guestLastName = form.querySelector('[data-guest-last-name]');
    const guestFullName = form.querySelector('[data-guest-full-name]');
    const scheduleDateTitle = form.querySelector('[data-schedule-date-title]');
    const schedulePrev = form.querySelector('[data-schedule-prev]');
    const scheduleNext = form.querySelector('[data-schedule-next]');
    const scheduleTopScroll = form.querySelector('[data-schedule-top-scroll]');
    const scheduleTopScrollInner = form.querySelector('[data-schedule-top-scroll-inner]');
    const scheduleTableWrap = form.querySelector('[data-schedule-table-wrap]');
    const scheduleHead = form.querySelector('[data-schedule-head]');
    const scheduleBody = form.querySelector('[data-schedule-body]');
    const scheduleSelectionEmpty = form.querySelector('[data-schedule-selection-empty]');
    const scheduleSelectionFilled = form.querySelector('[data-schedule-selection-filled]');
    const scheduleSelectionRoom = form.querySelector('[data-schedule-selection-room]');
    const scheduleSelectionMeta = form.querySelector('[data-schedule-selection-meta]');
    const scheduleSelectionTotal = form.querySelector('[data-schedule-selection-total]');
    const scheduleContinue = form.querySelector('[data-schedule-continue]');
    const scheduleCartPanel = form.querySelector('[data-schedule-cart-panel]');
    const scheduleCartList = form.querySelector('[data-schedule-cart-list]');
    const scheduleCartCount = form.querySelector('[data-schedule-cart-count]');
    const shouldShowCheckout = form.dataset.showCheckout === 'true';
    const initialStartTime = startSelect.value;
    const initialEndTime = endSelect.value;

    if (!roomSelect || !bookingDateInput || !bookingModeInput || !scheduleItemsInput || !startSelect || !endSelect || !bookingPicker || !bookingCheckout || !checkoutBack || !durationDisplay || !downpaymentInput || !paymentMethod || !paymentMethodCards.length || !roomMeta || !messageBody || !quoteTotal || !quoteMinimum || !quoteBalance || !quoteMeta || !paymentGcash || !paymentBank || !paymentInstructions || !roomCards.length || !roomRail || !calendarTitle || !calendarDays || !calendarPrev || !calendarNext || !slotDateTitle || !slotContinue || !selectedRoomName || !selectedRoomSpace || !selectedRoomRate || !startSlots || !endSlots || !startStep || !startSummary || !startSummaryTime || !startSummaryChange || !inlineSummary || !summaryDate || !summaryStart || !summaryEnd || !summaryDuration || !summaryRate || !summaryTotal || !checkoutRoom || !checkoutDate || !checkoutStart || !checkoutEnd || !checkoutDuration || !checkoutStandardSummary || !checkoutScheduleCount || !checkoutScheduleList || !checkoutSubmit || !scheduleDateTitle || !schedulePrev || !scheduleNext || !scheduleHead || !scheduleBody || !scheduleSelectionEmpty || !scheduleSelectionFilled || !scheduleSelectionRoom || !scheduleSelectionMeta || !scheduleSelectionTotal || !scheduleContinue || !scheduleCartPanel || !scheduleCartList || !scheduleCartCount) {
        return;
    }

    let blockedDates = new Set();
    let currentQuote = null;
    let bookingMode = bookingModeInput.value || 'room';
    let scheduleCart = [];
    let currentMonth = (() => {
        const base = bookingDateInput.value ? new Date(`${bookingDateInput.value}T00:00:00`) : new Date();
        return new Date(base.getFullYear(), base.getMonth(), 1);
    })();

    const today = new Date();
    const todayValue = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
    const currentMinutes = (today.getHours() * 60) + today.getMinutes();

    const formatCurrency = (value) => `Php ${Number(value || 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;

    const formatDate = (value) => {
        if (!value) {
            return '';
        }

        const date = new Date(`${value}T00:00:00`);

        if (Number.isNaN(date.getTime())) {
            return value;
        }

        return new Intl.DateTimeFormat('en-PH', {
            month: 'long',
            day: 'numeric',
            year: 'numeric',
        }).format(date);
    };

    const formatMonthTitle = (date) => new Intl.DateTimeFormat('en-PH', {
        month: 'long',
        year: 'numeric',
    }).format(date);

    const formatScheduleDate = (value) => new Intl.DateTimeFormat('en-PH', {
        weekday: 'short',
        month: 'long',
        day: 'numeric',
    }).format(new Date(`${value}T00:00:00`));

    const durationLabel = (startValue, endValue) => {
        if (!startValue || !endValue) {
            return '';
        }

        const [startHour, startMinute] = startValue.split(':').map(Number);
        const [endHour, endMinute] = endValue.split(':').map(Number);

        if ([startHour, startMinute, endHour, endMinute].some(Number.isNaN)) {
            return '';
        }

        let startTotal = (startHour * 60) + startMinute;
        let endTotal = (endHour * 60) + endMinute;

        if (endTotal <= startTotal) {
            endTotal += 24 * 60;
        }

        const diff = endTotal - startTotal;
        const hours = Math.floor(diff / 60);
        const minutes = diff % 60;
        const parts = [];

        if (hours) {
            parts.push(`${hours} ${hours === 1 ? 'hour' : 'hours'}`);
        }

        if (minutes) {
            parts.push(`${minutes} mins`);
        }

        return parts.join(' ');
    };

    const timeValueToMinutes = (value) => {
        if (!value || !value.includes(':')) {
            return null;
        }

        const [hour, minute] = value.split(':').map(Number);

        if ([hour, minute].some(Number.isNaN)) {
            return null;
        }

        return (hour * 60) + minute;
    };

    const currentScheduleCutoffMinutes = () => {
        const now = new Date();
        const minutes = (now.getHours() * 60) + now.getMinutes();

        if (minutes % 30 === 0) {
            return minutes;
        }

        return minutes + (30 - (minutes % 30));
    };

    const slotLabelToMinutes = (label) => {
        const [start] = (label || '').split(' - ');
        const match = start?.trim().match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);

        if (!match) {
            return Number.MAX_SAFE_INTEGER;
        }

        let hour = Number(match[1]);
        const minute = Number(match[2]);
        const period = match[3].toUpperCase();

        if (period === 'PM' && hour !== 12) {
            hour += 12;
        }

        if (period === 'AM' && hour === 12) {
            hour = 0;
        }

        return (hour * 60) + minute;
    };

    const isPastScheduleLabelForToday = (label, bookingDate) => {
        if (bookingDate !== todayValue) {
            return false;
        }

        const labelMinutes = slotLabelToMinutes(label);

        if (!Number.isFinite(labelMinutes)) {
            return false;
        }

        return labelMinutes < currentMinutes;
    };

    const setSelectOptions = (select, items, selectedValue = '') => {
        select.innerHTML = '';

        items.forEach((item) => {
            const option = document.createElement('option');
            option.value = item.value;
            option.textContent = item.label;

            if (item.range_label) {
                option.dataset.rangeLabel = item.range_label;
            }

            if (item.duration_label) {
                option.dataset.durationLabel = item.duration_label;
            }

            select.append(option);
        });

        if (selectedValue) {
            select.value = selectedValue;
        }
    };

    const minimumDownpaymentForTotal = (total) => (total <= 1000 ? total / 2 : 500);

    const scheduleItemKey = (item) => [
        item.hyve_room_id,
        item.booking_date,
        item.start_time,
        item.end_time,
    ].join('|');

    const syncGuestFullName = () => {
        if (!guestFullName) {
            return;
        }

        const fullName = [guestFirstName?.value || '', guestLastName?.value || '']
            .map((value) => value.trim())
            .filter(Boolean)
            .join(' ');

        guestFullName.value = fullName;
    };

    const syncScheduleScrollbars = () => {
        if (!scheduleTopScroll || !scheduleTopScrollInner || !scheduleTableWrap) {
            return;
        }

        const targetWidth = scheduleTableWrap.scrollWidth;
        const visibleWidth = scheduleTableWrap.clientWidth;
        scheduleTopScrollInner.style.width = `${targetWidth}px`;
        scheduleTopScroll.classList.toggle('hidden', targetWidth <= visibleWidth + 2);
        scheduleTopScroll.scrollLeft = scheduleTableWrap.scrollLeft;
    };

    const syncScheduleItemsInput = () => {
        scheduleItemsInput.value = JSON.stringify(scheduleCart.map((item) => ({
            hyve_room_id: item.hyve_room_id,
            booking_date: item.booking_date,
            start_time: item.start_time,
            end_time: item.end_time,
            room_name: item.room_name,
            room_space: item.room_space,
            label: item.label,
            total_amount: item.total_amount,
        })));
    };

    const summarizeScheduleCart = () => {
        const total = scheduleCart.reduce((sum, item) => sum + Number(item.total_amount || 0), 0);
        const roomCount = new Set(scheduleCart.map((item) => item.hyve_room_id)).size;
        const dateCount = new Set(scheduleCart.map((item) => item.booking_date)).size;
        return {
            total,
            roomCount,
            dateCount,
            slotCount: scheduleCart.length,
            minimumDownpayment: scheduleCart.length ? minimumDownpaymentForTotal(total) : 0,
        };
    };

    const hydrateScheduleCartFromInput = async () => {
        try {
            const parsed = JSON.parse(scheduleItemsInput.value || '[]');

            if (!Array.isArray(parsed) || !parsed.length) {
                scheduleCart = [];
                return;
            }

            scheduleCart = parsed
                .filter((item) => item && typeof item === 'object')
                .map((item) => {
                    const roomCard = roomCards.find((card) => card.dataset.roomId === String(item.hyve_room_id));

                    return {
                        hyve_room_id: Number(item.hyve_room_id),
                        booking_date: item.booking_date,
                        start_time: item.start_time,
                        end_time: item.end_time,
                        room_name: item.room_name || roomCard?.dataset.roomName || 'Room',
                        room_space: item.room_space || roomCard?.dataset.roomSpace || '',
                        label: item.label || `${item.start_time} - ${item.end_time}`,
                        total_amount: Number(item.total_amount || 0),
                    };
                });
            syncScheduleItemsInput();
        } catch (error) {
            scheduleCart = [];
        }
    };

    const getSelectedRoomCard = () => roomCards.find((card) => card.dataset.roomId === roomSelect.value);

    const setBookingMode = (mode) => {
        bookingMode = mode;
        bookingModeInput.value = mode;
        modeTriggers.forEach((trigger) => {
            trigger.classList.toggle('booking-calendar-tab--active', trigger.dataset.bookingModeValue === mode);
        });
        modePanels.forEach((panel) => {
            panel.classList.toggle('hidden', panel.dataset.bookingModePanel !== mode);
        });
    };

    const filterPastStartTimes = (items) => {
        if (bookingDateInput.value !== todayValue) {
            return items;
        }

        return items.filter((item) => {
            const minutes = timeValueToMinutes(item.value);

            if (minutes === null) {
                return true;
            }

            return minutes >= currentMinutes;
        });
    };

    const updatePaymentDestination = () => {
        paymentGcash.classList.toggle('hidden', paymentMethod.value !== 'gcash');
        paymentBank.classList.toggle('hidden', paymentMethod.value !== 'bank_transfer');
        paymentMethodCards.forEach((card) => {
            card.classList.toggle('is-active', card.dataset.paymentChoice === paymentMethod.value);
        });
    };

    const showCheckout = () => {
        bookingPicker.classList.add('hidden');
        bookingCheckout.classList.remove('hidden');
    };

    const showPicker = () => {
        bookingCheckout.classList.add('hidden');
        bookingPicker.classList.remove('hidden');
    };

    const updateCheckoutSummary = () => {
        if (bookingMode === 'schedule' && scheduleCart.length) {
            const summary = summarizeScheduleCart();
            checkoutStandardSummary.classList.add('hidden');
            checkoutScheduleCount.classList.remove('hidden');
            checkoutScheduleList.classList.remove('hidden');
            checkoutScheduleCount.textContent = `${summary.slotCount} booking${summary.slotCount === 1 ? '' : 's'}`;
            checkoutScheduleList.innerHTML = '';

            scheduleCart
                .slice()
                .sort((a, b) => `${a.booking_date}${a.start_time}`.localeCompare(`${b.booking_date}${b.start_time}`))
                .forEach((item) => {
                    const entry = document.createElement('article');
                    entry.className = 'booking-checkout__schedule-item';
                    entry.innerHTML = `
                        <strong>${item.room_name} - ${item.room_space}</strong>
                        <span>${formatDate(item.booking_date)} - ${item.label} - 1 hour</span>
                        <em>${formatCurrency(item.total_amount)}</em>
                    `;
                    checkoutScheduleList.append(entry);
                });
            return;
        }

        checkoutStandardSummary.classList.remove('hidden');
        checkoutScheduleCount.classList.add('hidden');
        checkoutScheduleList.classList.add('hidden');
        checkoutScheduleList.innerHTML = '';
        const roomCard = getSelectedRoomCard();
        checkoutRoom.textContent = roomCard
            ? `${roomCard.dataset.roomName || 'Choose a room'} - ${roomCard.dataset.roomSpace || ''}`.trim()
            : 'Choose a room';
        checkoutDate.textContent = formatDate(bookingDateInput.value || todayValue);
        checkoutStart.textContent = startSelect.selectedOptions[0]?.textContent ?? startSelect.value ?? '--:--';
        checkoutEnd.textContent = endSelect.selectedOptions[0]?.textContent ?? endSelect.value ?? '--:--';
        checkoutDuration.textContent = durationLabel(startSelect.value, endSelect.value) || '--';
    };

    const updateBalance = () => {
        if (!currentQuote) {
            quoteBalance.textContent = 'Php 0.00';
            checkoutSubmit.textContent = 'Confirm & Pay Php 0.00';
            return;
        }

        const total = Number(currentQuote.total_amount || 0);
        const minimum = Number(currentQuote.minimum_downpayment_amount || 0);
        let current = Number(downpaymentInput.value || 0);

        if (Number.isNaN(current) || current < minimum) {
            current = minimum;
        }

        if (current > total) {
            current = total;
        }

        downpaymentInput.value = String(current);
        quoteBalance.textContent = formatCurrency(total - current);
        checkoutSubmit.textContent = `Confirm & Pay ${formatCurrency(current)}`;
    };

    const hideInlineSummary = () => {
        inlineSummary.classList.add('hidden');
        summaryDate.textContent = formatDate(bookingDateInput.value || todayValue);
        summaryStart.textContent = '--:--';
        summaryEnd.textContent = '--:--';
        summaryDuration.textContent = '--';
        summaryRate.textContent = '--';
        summaryTotal.textContent = 'Php 0.00';
    };

    const hideStartSummary = () => {
        startSummary.classList.add('hidden');
        startSummaryTime.textContent = '--:--';
        startStep.classList.remove('hidden');
    };

    const showStartSummary = () => {
        const startLabel = startSelect.selectedOptions[0]?.textContent ?? startSelect.value ?? '--:--';
        startSummaryTime.textContent = startLabel;
        startSummary.classList.remove('hidden');
        startStep.classList.add('hidden');
    };

    const removeScheduleItem = (key) => {
        scheduleCart = scheduleCart.filter((item) => scheduleItemKey(item) !== key);
        syncScheduleItemsInput();
        updateScheduleSelection();
        updateCheckoutSummary();
    };

    const pruneExpiredScheduleItems = () => {
        if (!scheduleCart.length) {
            return false;
        }

        const cutoffMinutes = currentScheduleCutoffMinutes();
        const nextCart = scheduleCart.filter((item) => {
            if (item.booking_date !== todayValue) {
                return true;
            }

            const startMinutes = timeValueToMinutes(item.start_time);

            return startMinutes === null || startMinutes >= cutoffMinutes;
        });

        if (nextCart.length === scheduleCart.length) {
            return false;
        }

        scheduleCart = nextCart;
        syncScheduleItemsInput();
        updateScheduleSelection();
        updateCheckoutSummary();
        messageBody.textContent = 'Some selected current-day schedule slots already started or expired, so they were removed from your cart. Please review your booking again.';

        return true;
    };

    const renderScheduleCart = () => {
        scheduleCartList.innerHTML = '';
        scheduleCartPanel.classList.toggle('hidden', scheduleCart.length === 0);
        scheduleCartCount.textContent = `${scheduleCart.length} item${scheduleCart.length === 1 ? '' : 's'}`;

        if (!scheduleCart.length) {
            return;
        }

        scheduleCart
            .slice()
            .sort((a, b) => `${a.booking_date}${a.start_time}`.localeCompare(`${b.booking_date}${b.start_time}`))
            .forEach((item) => {
                const row = document.createElement('div');
                row.className = 'booking-schedule__cart-item';
                row.innerHTML = `
                    <div class="booking-schedule__cart-copy">
                        <strong>${item.room_name} - ${item.room_space}</strong>
                        <span>${formatDate(item.booking_date)} - ${item.label} - 1 hour</span>
                    </div>
                    <div class="booking-schedule__cart-meta">
                        <strong>${formatCurrency(item.total_amount)}</strong>
                        <button type="button" class="booking-schedule__cart-remove" aria-label="Remove schedule item">&times;</button>
                    </div>
                `;

                row.querySelector('button')?.addEventListener('click', async () => {
                    removeScheduleItem(scheduleItemKey(item));

                    if (bookingMode === 'schedule') {
                        await renderSchedule();
                    }
                });

                scheduleCartList.append(row);
            });
    };

    const updateScheduleSelection = () => {
        syncScheduleItemsInput();
        renderScheduleCart();

        if (bookingMode !== 'schedule') {
            return;
        }

        if (!scheduleCart.length) {
            scheduleSelectionEmpty.classList.remove('hidden');
            scheduleSelectionFilled.classList.add('hidden');
            scheduleSelectionRoom.textContent = '0 slots selected';
            scheduleSelectionMeta.textContent = 'Pick an available hour to continue.';
            scheduleSelectionTotal.textContent = 'Php 0.00';
            scheduleContinue.disabled = true;
            currentQuote = null;
            quoteTotal.textContent = 'Php 0.00';
            quoteMinimum.textContent = 'Php 0.00';
            quoteBalance.textContent = 'Php 0.00';
            quoteMeta.textContent = 'Choose one or more schedule slots first to load your live rate summary.';
            checkoutSubmit.textContent = 'Confirm & Pay Php 0.00';
            return;
        }

        const summary = summarizeScheduleCart();
        currentQuote = {
            total_amount: summary.total,
            minimum_downpayment_amount: summary.minimumDownpayment,
        };

        scheduleSelectionEmpty.classList.add('hidden');
        scheduleSelectionFilled.classList.remove('hidden');
        scheduleSelectionRoom.textContent = `${summary.slotCount} selected slot${summary.slotCount === 1 ? '' : 's'} across ${summary.roomCount} room${summary.roomCount === 1 ? '' : 's'}`;
        scheduleSelectionMeta.textContent = summary.dateCount === 1
            ? `Booking date: ${formatDate(scheduleCart[0].booking_date)}`
            : `${summary.dateCount} booking dates selected`;
        scheduleSelectionTotal.textContent = formatCurrency(summary.total);
        quoteTotal.textContent = formatCurrency(summary.total);
        quoteMinimum.textContent = formatCurrency(summary.minimumDownpayment);
        quoteMeta.textContent = `Full schedule cart | ${summary.slotCount} hour slot(s) | ${summary.roomCount} room(s).`;

        if (!downpaymentInput.value || Number(downpaymentInput.value) < summary.minimumDownpayment) {
            downpaymentInput.value = String(summary.minimumDownpayment);
        }

        downpaymentInput.min = String(summary.minimumDownpayment);
        updateBalance();
        scheduleContinue.disabled = false;
    };

    const resetQuote = () => {
        currentQuote = null;
        quoteTotal.textContent = 'Php 0.00';
        quoteMinimum.textContent = 'Php 0.00';
        quoteBalance.textContent = 'Php 0.00';
        quoteMeta.textContent = 'Choose a room, date, start time, and end time first to load your live rate summary.';
        hideInlineSummary();
        slotContinue.textContent = 'Pick a time to continue';
        slotContinue.disabled = true;
        checkoutSubmit.textContent = 'Confirm & Pay Php 0.00';
        updateScheduleSelection();
    };

    const resetSlots = (message = 'Select a room and date first. Available times will appear here.') => {
        startSelect.innerHTML = '';
        endSelect.innerHTML = '';
        startSlots.innerHTML = '<span class="booking-slot-empty">No start times loaded yet.</span>';
        endSlots.innerHTML = '<span class="booking-slot-empty">Select a start time first.</span>';
        durationDisplay.textContent = 'Choose a start time first to continue.';
        messageBody.textContent = message;
        hideStartSummary();
        hideInlineSummary();
        updateCheckoutSummary();
        updateScheduleSelection();
    };

    const updateRoomMeta = () => {
        const roomCard = getSelectedRoomCard();

        if (!roomCard) {
            roomMeta.textContent = 'Choose the exact room first, then pick an available date and start time.';
            selectedRoomName.textContent = 'Choose a room';
            selectedRoomSpace.textContent = '';
            selectedRoomRate.textContent = 'Ask HYVE';
            checkoutRoom.textContent = 'Choose a room';
            return;
        }

        roomMeta.textContent = `${roomCard.dataset.roomDescription} | ${roomCard.dataset.roomSpace}`;
        selectedRoomName.textContent = roomCard.dataset.roomName || 'Choose a room';
        selectedRoomSpace.textContent = roomCard.dataset.roomSpace || '';
        selectedRoomRate.textContent = roomCard.dataset.roomRate || 'Ask HYVE';
        checkoutRoom.textContent = `${roomCard.dataset.roomName || 'Choose a room'} - ${roomCard.dataset.roomSpace || ''}`.trim();
    };

    const updateSlotHeading = () => {
        slotDateTitle.textContent = formatDate(bookingDateInput.value || todayValue);
        checkoutDate.textContent = formatDate(bookingDateInput.value || todayValue);
        scheduleDateTitle.textContent = formatScheduleDate(bookingDateInput.value || todayValue);
        schedulePrev.disabled = (bookingDateInput.value || todayValue) <= todayValue;
    };

    const setActiveRoom = (roomId) => {
        roomSelect.value = roomId;
        roomCards.forEach((card) => {
            card.classList.toggle('is-active', card.dataset.roomId === roomId);
        });
        updateRoomMeta();
        updateScheduleSelection();
    };

    const renderCalendar = () => {
        calendarTitle.textContent = formatMonthTitle(currentMonth);
        calendarDays.innerHTML = '';

        const firstDay = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), 1);
        const lastDay = new Date(currentMonth.getFullYear(), currentMonth.getMonth() + 1, 0);
        const leading = firstDay.getDay();

        for (let i = 0; i < leading; i += 1) {
            const filler = document.createElement('span');
            filler.className = 'booking-calendar-day is-filler';
            calendarDays.append(filler);
        }

        for (let day = 1; day <= lastDay.getDate(); day += 1) {
            const date = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), day);
            const value = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'booking-calendar-day';
            button.textContent = String(day);

            if (value === todayValue) {
                button.classList.add('is-today');
            }

            if (bookingDateInput.value === value) {
                button.classList.add('is-selected');
            }

            if (blockedDates.has(value)) {
                button.classList.add('is-booked');
            }

            if (value < todayValue || blockedDates.has(value)) {
                button.disabled = true;
            }

            button.addEventListener('click', async () => {
                bookingDateInput.value = value;
                updateSlotHeading();
                renderCalendar();

                try {
                    await fetchStartTimes();

                    if (bookingMode === 'schedule') {
                        await renderSchedule();
                    }
                } catch (error) {
                    messageBody.textContent = 'Unable to load date availability right now. Please try again.';
                }
            });

            calendarDays.append(button);
        }
    };

    const renderSlotButtons = (container, items, type) => {
        container.innerHTML = '';

        if (!items.length) {
            const empty = document.createElement('span');
            empty.className = 'booking-slot-empty';
            empty.textContent = type === 'start' ? 'No available start times for this date.' : 'Select a start time first.';
            container.append(empty);
            return;
        }

        items.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'booking-slot-pill';
            button.innerHTML = `<strong>${item.label}</strong>${item.duration_label ? `<small>${item.duration_label}</small>` : ''}`;

            if ((type === 'start' && startSelect.value === item.value) || (type === 'end' && endSelect.value === item.value)) {
                button.classList.add('is-active');
            }

            button.addEventListener('click', async () => {
                if (type === 'start') {
                    startSelect.value = item.value;
                    endSelect.innerHTML = '';
                    showStartSummary();
                    hideInlineSummary();
                    resetQuote();

                    try {
                        await fetchEndTimes();
                    } catch (error) {
                        messageBody.textContent = 'Unable to load end time options right now. Please try again.';
                    }

                    return;
                }

                endSelect.value = item.value;
                durationDisplay.textContent = `${item.duration_label} | ${item.range_label}`;

                try {
                    await fetchQuote();
                    renderSlotButtons(container, items, 'end');
                } catch (error) {
                    messageBody.textContent = 'Unable to load the booking quote right now. Please try again.';
                }
            });

            container.append(button);
        });
    };

    const fetchJson = async (url) => {
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('Request failed.');
        }

        return response.json();
    };

    const fetchUnavailableDates = async () => {
        if (!roomSelect.value) {
            blockedDates = new Set();
            renderCalendar();
            return;
        }

        const data = await fetchJson(`${unavailableDatesUrl}?hyve_room_id=${encodeURIComponent(roomSelect.value)}&horizon_days=${encodeURIComponent(horizonDays)}`);
        blockedDates = new Set((Array.isArray(data.unavailable_dates) ? data.unavailable_dates : []).map((item) => item.value));
        renderCalendar();
    };

    const fetchStartTimes = async () => {
        if (!roomSelect.value || !bookingDateInput.value) {
            resetSlots();
            resetQuote();
            return;
        }

        if (blockedDates.has(bookingDateInput.value)) {
            resetSlots('This room is fully booked for the selected date. Please choose another day.');
            resetQuote();
            return;
        }

        const data = await fetchJson(`${availabilityUrl}?hyve_room_id=${encodeURIComponent(roomSelect.value)}&booking_date=${encodeURIComponent(bookingDateInput.value)}`);
        const startTimes = filterPastStartTimes(Array.isArray(data.start_times) ? data.start_times : []);

        setSelectOptions(startSelect, startTimes);
        endSelect.innerHTML = '';
        endSlots.innerHTML = '<span class="booking-slot-empty">Select a start time first.</span>';
        durationDisplay.textContent = 'Choose a start time first to continue.';
        hideStartSummary();
        hideInlineSummary();
        resetQuote();
        renderSlotButtons(startSlots, startTimes, 'start');

        if (!startTimes.length) {
            messageBody.textContent = bookingDateInput.value === todayValue
                ? 'No more booking windows are available for the rest of today.'
                : 'No booking windows are available for that room on the selected date.';
            return;
        }

        messageBody.textContent = `${startTimes.length} start ${startTimes.length === 1 ? 'time is' : 'times are'} available on ${formatDate(bookingDateInput.value)}.`;
    };

    const fetchEndTimes = async () => {
        if (!roomSelect.value || !bookingDateInput.value || !startSelect.value) {
            endSelect.innerHTML = '';
            endSlots.innerHTML = '<span class="booking-slot-empty">Select a start time first.</span>';
            durationDisplay.textContent = 'Choose a start time first to continue.';
            resetQuote();
            return;
        }

        const data = await fetchJson(`${availabilityUrl}?hyve_room_id=${encodeURIComponent(roomSelect.value)}&booking_date=${encodeURIComponent(bookingDateInput.value)}&start_time=${encodeURIComponent(startSelect.value)}`);
        const endTimes = Array.isArray(data.end_times) ? data.end_times : [];

        setSelectOptions(endSelect, endTimes);
        showStartSummary();
        renderSlotButtons(
            startSlots,
            Array.from(startSelect.options).map((option) => ({ value: option.value, label: option.textContent })).filter((item) => item.value),
            'start',
        );
        renderSlotButtons(endSlots, endTimes, 'end');

        if (!endTimes.length) {
            durationDisplay.textContent = `Minimum booking is ${minimumDuration / 60} hour.`;
            resetQuote();
            messageBody.textContent = 'No valid end time is available for that start time. Please choose another start time.';
            return;
        }

        messageBody.textContent = 'Choose how long you want to stay. The available end times are ready below.';
    };

    const fetchQuote = async () => {
        if (!roomSelect.value || !bookingDateInput.value || !startSelect.value || !endSelect.value) {
            resetQuote();
            return;
        }

        const data = await fetchJson(`${quoteUrl}?hyve_room_id=${encodeURIComponent(roomSelect.value)}&booking_date=${encodeURIComponent(bookingDateInput.value)}&start_time=${encodeURIComponent(startSelect.value)}&end_time=${encodeURIComponent(endSelect.value)}`);

        currentQuote = data;
        quoteTotal.textContent = formatCurrency(data.total_amount);
        quoteMinimum.textContent = formatCurrency(data.minimum_downpayment_amount);
        quoteMeta.textContent = `${data.rate_name} | ${data.charge_period_label} | ${data.duration_hours} scheduled hour(s) | ${data.billed_hours} billed hour(s).`;
        paymentInstructions.textContent = data.payment?.instructions || paymentInstructions.textContent;
        downpaymentInput.min = String(data.minimum_downpayment_amount);

        if (!downpaymentInput.value || Number(downpaymentInput.value) < Number(data.minimum_downpayment_amount)) {
            downpaymentInput.value = String(data.minimum_downpayment_amount);
        }

        durationDisplay.textContent = endSelect.selectedOptions[0]?.dataset.durationLabel
            ? `${endSelect.selectedOptions[0].dataset.durationLabel} | ${endSelect.selectedOptions[0].dataset.rangeLabel}`
            : durationLabel(startSelect.value, endSelect.value);

        summaryDate.textContent = formatDate(bookingDateInput.value || todayValue);
        summaryStart.textContent = startSelect.selectedOptions[0]?.textContent ?? startSelect.value;
        summaryEnd.textContent = endSelect.selectedOptions[0]?.textContent ?? endSelect.value;
        summaryDuration.textContent = data.duration_hours === 1 ? '1 hour' : `${data.duration_hours} hours`;
        summaryRate.textContent = `${data.rate_name} - ${data.charge_period_label}`;
        summaryTotal.textContent = formatCurrency(data.total_amount);
        inlineSummary.classList.remove('hidden');
        checkoutStart.textContent = startSelect.selectedOptions[0]?.textContent ?? startSelect.value;
        checkoutEnd.textContent = endSelect.selectedOptions[0]?.textContent ?? endSelect.value;
        checkoutDuration.textContent = data.duration_hours === 1 ? '1 hour' : `${data.duration_hours} hours`;

        updateBalance();
        updateCheckoutSummary();
        updateScheduleSelection();
        messageBody.textContent = 'Your booking window and quote are ready. Review the payment details before submitting.';
        slotContinue.textContent = 'Continue to checkout ->';
        slotContinue.disabled = false;
    };

    const syncScheduleSelection = async (room, startSlot, bookingDate, finalEndTime) => {
        setActiveRoom(String(room.id));
        bookingDateInput.value = bookingDate;
        currentMonth = new Date(`${bookingDate}T00:00:00`);
        updateSlotHeading();
        renderCalendar();
        await fetchUnavailableDates();
        await fetchStartTimes();

        startSelect.value = startSlot.value;
        renderSlotButtons(
            startSlots,
            Array.from(startSelect.options).map((option) => ({ value: option.value, label: option.textContent })).filter((item) => item.value),
            'start',
        );
        showStartSummary();

        await fetchEndTimes();
        endSelect.value = finalEndTime;
        renderSlotButtons(
            endSlots,
            Array.from(endSelect.options).map((option) => ({
                value: option.value,
                label: option.textContent,
                range_label: option.dataset.rangeLabel,
                duration_label: option.dataset.durationLabel,
            })).filter((item) => item.value),
            'end',
        );
        await fetchQuote();
    };

    const renderSchedule = async ({ showLoading = true } = {}) => {
        updateSlotHeading();
        scheduleHead.innerHTML = '';
        scheduleBody.innerHTML = '';

        if (showLoading) {
            scheduleBody.innerHTML = '<tr><td class="booking-schedule__loading" colspan="99">Loading full schedule...</td></tr>';
        }

        const data = await fetchJson(`${layoutUrl}?booking_date=${encodeURIComponent(bookingDateInput.value)}`);
        const rooms = Array.isArray(data.rooms) ? data.rooms : [];
        const roomSlotMaps = new Map();
        const rows = [...new Set(
            rooms.flatMap((room) => [
                ...(Array.isArray(room.available_slots) ? room.available_slots.map((slot) => slot.label) : []),
                ...(Array.isArray(room.booked_slots) ? room.booked_slots.map((slot) => slot.label) : []),
            ]),
        )]
            .filter((label) => !isPastScheduleLabelForToday(label, data.booking_date))
            .sort((a, b) => slotLabelToMinutes(a) - slotLabelToMinutes(b));

        const headRow = document.createElement('tr');
        headRow.innerHTML = '<th>Time</th>';

        rooms.forEach((room) => {
            const roomCard = roomCards.find((card) => card.dataset.roomId === String(room.id));
            const image = roomCard?.querySelector('img')?.getAttribute('src') || '';
            const header = document.createElement('th');
            header.innerHTML = `
                <div class="booking-schedule__room-head">
                    ${image ? `<img src="${image}" alt="${room.room_name}">` : ''}
                    <strong>${room.room_name}</strong>
                    <small>${room.space_label || ''}</small>
                </div>
            `;
            headRow.append(header);
        });

        scheduleHead.append(headRow);
        scheduleBody.innerHTML = '';

        if (!rows.length) {
            scheduleBody.innerHTML = '<tr><td class="booking-schedule__loading" colspan="99">No booking windows are available for this day yet.</td></tr>';
            updateScheduleSelection();
            syncScheduleScrollbars();
            return;
        }

        rooms.forEach((room) => {
            const availableSlots = Array.isArray(room.available_slots) ? room.available_slots : [];
            roomSlotMaps.set(String(room.id), new Map(
                availableSlots.map((slot) => [slot.value, slot]),
            ));
        });

        rows.forEach((label) => {
            const tr = document.createElement('tr');
            const th = document.createElement('th');
            th.textContent = label;
            tr.append(th);

            rooms.forEach((room) => {
                const td = document.createElement('td');
                const availableSlot = Array.isArray(room.available_slots)
                    ? room.available_slots.find((slot) => slot.label === label)
                    : null;
                const isPastCurrentDaySlot = isPastScheduleLabelForToday(label, data.booking_date);

                if (availableSlot && !isPastCurrentDaySlot) {
                    const button = document.createElement('button');
                    const roomCard = roomCards.find((card) => card.dataset.roomId === String(room.id));
                    const itemKey = scheduleItemKey({
                        hyve_room_id: room.id,
                        booking_date: data.booking_date,
                        start_time: availableSlot.value,
                        end_time: availableSlot.end_time,
                    });
                    const isSelected = scheduleCart.some((item) => scheduleItemKey(item) === itemKey);

                    button.type = 'button';
                    button.className = `booking-schedule__slot is-available${isSelected ? ' is-selected' : ''}`;
                    button.innerHTML = `
                        <span class="booking-schedule__slot-dot">${isSelected ? '&#10003;' : ''}</span>
                        <strong class="booking-schedule__slot-price">${roomCard?.dataset.roomRate || 'Available'}</strong>
                    `;
                    button.addEventListener('click', async () => {
                        try {
                            if (isSelected) {
                                removeScheduleItem(itemKey);
                                await renderSchedule({ showLoading: false });
                                return;
                            }

                            const quote = await fetchJson(`${quoteUrl}?hyve_room_id=${encodeURIComponent(room.id)}&booking_date=${encodeURIComponent(data.booking_date)}&start_time=${encodeURIComponent(availableSlot.value)}&end_time=${encodeURIComponent(availableSlot.end_time)}`);

                            scheduleCart.push({
                                hyve_room_id: room.id,
                                booking_date: data.booking_date,
                                start_time: availableSlot.value,
                                end_time: availableSlot.end_time,
                                room_name: room.room_name,
                                room_space: room.space_label || '',
                                label: availableSlot.label,
                                total_amount: Number(quote.total_amount || 0),
                            });

                            syncScheduleItemsInput();
                            updateScheduleSelection();
                            updateCheckoutSummary();
                            await renderSchedule({ showLoading: false });
                        } catch (error) {
                            messageBody.textContent = 'Unable to load the selected schedule slot right now. Please try again.';
                        }
                    });
                    td.append(button);
                } else {
                    const blocked = document.createElement('span');
                    blocked.className = 'booking-schedule__slot is-unavailable';
                    blocked.innerHTML = `
                        <span class="booking-schedule__slot-dot">&times;</span>
                        <strong class="booking-schedule__slot-price">Booked</strong>
                    `;
                    td.append(blocked);
                }

                tr.append(td);
            });

            scheduleBody.append(tr);
        });

        updateScheduleSelection();
        syncScheduleScrollbars();
    };

    const shiftScheduleDate = async (days) => {
        const date = new Date(`${bookingDateInput.value}T00:00:00`);
        date.setDate(date.getDate() + days);
        const nextValue = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

        if (nextValue < todayValue) {
            return;
        }

        bookingDateInput.value = nextValue;
        currentMonth = new Date(date.getFullYear(), date.getMonth(), 1);
        updateSlotHeading();
        renderCalendar();

        try {
            await fetchUnavailableDates();
            await fetchStartTimes();
            await renderSchedule();
        } catch (error) {
            scheduleBody.innerHTML = '<tr><td class="booking-schedule__loading" colspan="99">Unable to load the full schedule right now.</td></tr>';
        }
    };

    roomCards.forEach((card) => {
        card.addEventListener('click', async () => {
            setActiveRoom(card.dataset.roomId || '');

            try {
                await fetchUnavailableDates();
                await fetchStartTimes();

                if (bookingMode === 'schedule') {
                    await renderSchedule();
                }
            } catch (error) {
                messageBody.textContent = 'Unable to load room availability right now. Please try again.';
            }
        });
    });

    modeTriggers.forEach((trigger) => {
        trigger.addEventListener('click', async () => {
            const mode = trigger.dataset.bookingModeValue || 'room';
            setBookingMode(mode);

            if (mode === 'schedule') {
                try {
                    await renderSchedule();
                } catch (error) {
                    scheduleBody.innerHTML = '<tr><td class="booking-schedule__loading" colspan="99">Unable to load the full schedule right now.</td></tr>';
                }
            }
        });
    });

    paymentMethodCards.forEach((card) => {
        card.addEventListener('click', () => {
            paymentMethod.value = card.dataset.paymentChoice || '';
            updatePaymentDestination();
        });
    });

    paymentMethod.addEventListener('change', updatePaymentDestination);
    downpaymentInput.addEventListener('input', updateBalance);

    if (scheduleTopScroll && scheduleTableWrap) {
        let syncingScheduleScroll = false;

        scheduleTopScroll.addEventListener('scroll', () => {
            if (syncingScheduleScroll) {
                return;
            }

            syncingScheduleScroll = true;
            scheduleTableWrap.scrollLeft = scheduleTopScroll.scrollLeft;
            syncingScheduleScroll = false;
        });

        scheduleTableWrap.addEventListener('scroll', () => {
            if (syncingScheduleScroll) {
                return;
            }

            syncingScheduleScroll = true;
            scheduleTopScroll.scrollLeft = scheduleTableWrap.scrollLeft;
            syncingScheduleScroll = false;
        });

        window.addEventListener('resize', syncScheduleScrollbars);
    }

    if (guestFirstName) {
        guestFirstName.addEventListener('input', syncGuestFullName);
    }

    if (guestLastName) {
        guestLastName.addEventListener('input', syncGuestFullName);
    }
    roomScrollPrev?.addEventListener('click', () => {
        roomRail.scrollBy({ left: -320, behavior: 'smooth' });
    });
    roomScrollNext?.addEventListener('click', () => {
        roomRail.scrollBy({ left: 320, behavior: 'smooth' });
    });
    startSummaryChange.addEventListener('click', async () => {
        endSelect.innerHTML = '';
        endSlots.innerHTML = '<span class="booking-slot-empty">Select a new start time.</span>';
        startSelect.value = '';
        durationDisplay.textContent = 'Choose a start time first to continue.';
        hideStartSummary();
        resetQuote();

        try {
            await fetchStartTimes();

            if (bookingMode === 'schedule') {
                await renderSchedule();
            }
        } catch (error) {
            messageBody.textContent = 'Unable to reload start times right now. Please try again.';
        }
    });
    slotContinue.addEventListener('click', () => {
        if (slotContinue.disabled) {
            return;
        }

        updateCheckoutSummary();
        showCheckout();
    });
    scheduleContinue.addEventListener('click', () => {
        if (scheduleContinue.disabled) {
            return;
        }

        const removedExpiredSlots = pruneExpiredScheduleItems();

        if (removedExpiredSlots) {
            return;
        }

        updateCheckoutSummary();
        showCheckout();
    });

    form.addEventListener('submit', async (event) => {
        if (bookingMode !== 'schedule') {
            return;
        }

        const removedExpiredSlots = pruneExpiredScheduleItems();

        if (!removedExpiredSlots) {
            return;
        }

        event.preventDefault();
        showPicker();
        await renderSchedule();
    });
    checkoutBack.addEventListener('click', () => {
        showPicker();
    });

    calendarPrev.addEventListener('click', () => {
        currentMonth = new Date(currentMonth.getFullYear(), currentMonth.getMonth() - 1, 1);
        renderCalendar();
    });

    calendarNext.addEventListener('click', () => {
        currentMonth = new Date(currentMonth.getFullYear(), currentMonth.getMonth() + 1, 1);
        renderCalendar();
    });

    schedulePrev.addEventListener('click', async () => {
        await shiftScheduleDate(-1);
    });

    scheduleNext.addEventListener('click', async () => {
        await shiftScheduleDate(1);
    });

    setBookingMode(bookingMode);
    updatePaymentDestination();
    syncGuestFullName();
    updateSlotHeading();
    updateRoomMeta();
    updateCheckoutSummary();
    updateScheduleSelection();
    renderCalendar();
    resetSlots();
    resetQuote();

    if (roomSelect.value) {
        setActiveRoom(roomSelect.value);
        fetchUnavailableDates()
            .then(fetchStartTimes)
            .then(async () => {
                if (!initialStartTime) {
                    return;
                }

                startSelect.value = initialStartTime;
                showStartSummary();
                await fetchEndTimes();

                if (!initialEndTime) {
                    return;
                }

                endSelect.value = initialEndTime;
                await fetchQuote();
            })
            .catch(() => {
                messageBody.textContent = 'Unable to load saved booking values right now. Please reselect your details.';
            });
    }

    if (shouldShowCheckout) {
        showCheckout();
    }

    hydrateScheduleCartFromInput().then(async () => {
        updateScheduleSelection();
        updateCheckoutSummary();

        if (bookingMode === 'schedule') {
            try {
                await renderSchedule();
            } catch (error) {
                scheduleBody.innerHTML = '<tr><td class="booking-schedule__loading" colspan="99">Unable to load the full schedule right now.</td></tr>';
            }
        }
    });
};

document.addEventListener('DOMContentLoaded', () => {
    setupNav();
    setupSpacesBrowser();
    setupReveal();
    setupBookingPageV2();
});
