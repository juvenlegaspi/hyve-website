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

const setupAmenitiesGallery = () => {
    const gallery = document.querySelector('[data-amenities-gallery]');

    if (!gallery) {
        return;
    }

    const slides = [...gallery.querySelectorAll('[data-amenities-slide]')];
    const prev = gallery.querySelector('[data-amenities-prev]');
    const next = gallery.querySelector('[data-amenities-next]');

    if (!slides.length || !prev || !next) {
        return;
    }

    let currentIndex = slides.findIndex((slide) => slide.classList.contains('is-active'));

    if (currentIndex < 0) {
        currentIndex = 0;
    }

    const syncThumbs = (slide) => {
        const mainImage = slide.querySelector('[data-amenities-main-image]');
        const thumbs = [...slide.querySelectorAll('[data-amenities-thumb]')];

        if (!mainImage || !thumbs.length) {
            return;
        }

        thumbs.forEach((thumb) => {
            thumb.classList.toggle('is-active', thumb.dataset.imageSrc === mainImage.getAttribute('src'));
        });
    };

    const showSlide = (index) => {
        currentIndex = (index + slides.length) % slides.length;

        slides.forEach((slide, slideIndex) => {
            slide.classList.toggle('is-active', slideIndex === currentIndex);
        });

        syncThumbs(slides[currentIndex]);
    };

    slides.forEach((slide) => {
        const mainImage = slide.querySelector('[data-amenities-main-image]');

        slide.querySelectorAll('[data-amenities-thumb]').forEach((thumb) => {
            thumb.addEventListener('click', () => {
                if (!mainImage || !thumb.dataset.imageSrc) {
                    return;
                }

                mainImage.setAttribute('src', thumb.dataset.imageSrc);
                syncThumbs(slide);
            });
        });
    });

    prev.addEventListener('click', () => showSlide(currentIndex - 1));
    next.addEventListener('click', () => showSlide(currentIndex + 1));
    showSlide(currentIndex);
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

const setupMemberMenu = () => {
    const menus = [...document.querySelectorAll('[data-member-menu]')];

    if (!menus.length) {
        return;
    }

    const closeMenu = (menu) => {
        const panel = menu.querySelector('[data-member-panel]');
        const toggle = menu.querySelector('[data-member-toggle]');

        if (!panel || !toggle) {
            return;
        }

        panel.classList.add('hidden');
        toggle.setAttribute('aria-expanded', 'false');
    };

    const openMenu = (menu) => {
        const panel = menu.querySelector('[data-member-panel]');
        const toggle = menu.querySelector('[data-member-toggle]');

        if (!panel || !toggle) {
            return;
        }

        menus.forEach((item) => {
            if (item !== menu) {
                closeMenu(item);
            }
        });

        panel.classList.remove('hidden');
        toggle.setAttribute('aria-expanded', 'true');
    };

    menus.forEach((menu) => {
        const toggle = menu.querySelector('[data-member-toggle]');
        const panel = menu.querySelector('[data-member-panel]');

        if (!toggle || !panel) {
            return;
        }

        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            const isOpen = !panel.classList.contains('hidden');

            if (isOpen) {
                closeMenu(menu);
                return;
            }

            openMenu(menu);
        });

        panel.addEventListener('click', (event) => {
            event.stopPropagation();
        });
    });

    document.addEventListener('click', () => {
        menus.forEach(closeMenu);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            menus.forEach(closeMenu);
        }
    });
};

const setupMemberBookingsTabs = () => {
    const wrapper = document.querySelector('[data-bookings-tabs]');

    if (!wrapper) {
        return;
    }

    const tabs = [...wrapper.querySelectorAll('[data-bookings-tab]')];
    const panels = [...wrapper.querySelectorAll('[data-bookings-panel]')];

    const activate = (target) => {
        tabs.forEach((tab) => {
            tab.classList.toggle('is-active', tab.dataset.bookingsTab === target);
        });

        panels.forEach((panel) => {
            const isActive = panel.dataset.bookingsPanel === target;
            panel.classList.toggle('hidden', !isActive);
            panel.classList.toggle('is-active', isActive);
        });
    };

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            activate(tab.dataset.bookingsTab || 'upcoming');
        });
    });
};

const setupAdminRoomModals = () => {
    const triggers = [...document.querySelectorAll('[data-room-modal-open]')];

    if (!triggers.length) {
        return;
    }

    const closeModal = (modal) => {
        if (!modal) {
            return;
        }

        modal.classList.add('hidden');
        document.body.classList.remove('modal-open');
    };

    const openModal = (modal) => {
        if (!modal) {
            return;
        }

        modal.classList.remove('hidden');
        document.body.classList.add('modal-open');
    };

    triggers.forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const modal = document.getElementById(trigger.dataset.roomModalOpen || '');
            openModal(modal);
        });
    });

    document.querySelectorAll('[data-room-modal]').forEach((modal) => {
        modal.querySelectorAll('[data-room-modal-close]').forEach((closer) => {
            closer.addEventListener('click', () => closeModal(modal));
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        document.querySelectorAll('[data-room-modal]').forEach((modal) => {
            if (!modal.classList.contains('hidden')) {
                closeModal(modal);
            }
        });
    });
};

const setupMemberBookingModal = () => {
    const modal = document.querySelector('[data-booking-modal]');
    const triggers = [...document.querySelectorAll('[data-booking-open]')];

    if (!modal || !triggers.length) {
        return;
    }

    const room = modal.querySelector('[data-booking-modal-room]');
    const space = modal.querySelector('[data-booking-modal-space]');
    const date = modal.querySelector('[data-booking-modal-date]');
    const time = modal.querySelector('[data-booking-modal-time]');
    const duration = modal.querySelector('[data-booking-modal-duration]');
    const payment = modal.querySelector('[data-booking-modal-payment]');
    const status = modal.querySelector('[data-booking-modal-status]');
    const amount = modal.querySelector('[data-booking-modal-amount]');
    const balance = modal.querySelector('[data-booking-modal-balance]');
    const downpayment = modal.querySelector('[data-booking-modal-downpayment]');
    const reference = modal.querySelector('[data-booking-modal-reference]');
    const wifiWrap = modal.querySelector('[data-booking-modal-wifi-wrap]');
    const wifiCode = modal.querySelector('[data-booking-modal-wifi-code]');
    const wifiWindow = modal.querySelector('[data-booking-modal-wifi-window]');
    const wifiMeta = modal.querySelector('[data-booking-modal-wifi-meta]');
    const meta = modal.querySelector('[data-booking-modal-meta]');
    const actions = modal.querySelector('[data-booking-modal-actions]');
    const cancelForm = modal.querySelector('[data-booking-cancel-form]');
    const cancelWarning = modal.querySelector('[data-booking-cancel-warning]');
    const balanceLink = modal.querySelector('[data-booking-balance-link]');
    const rescheduleLink = modal.querySelector('[data-booking-reschedule-link]');
    const closers = [...modal.querySelectorAll('[data-booking-close]')];

    const closeModal = () => {
        modal.classList.add('hidden');
        document.body.classList.remove('modal-open');
    };

    const openModal = (trigger) => {
        room.textContent = trigger.dataset.bookingRoom || 'Booking';
        space.textContent = trigger.dataset.bookingSpace || 'HYVE Workspace';
        date.textContent = trigger.dataset.bookingDate || '-';
        time.textContent = trigger.dataset.bookingTime || '-';
        duration.textContent = trigger.dataset.bookingDuration || '-';
        payment.textContent = trigger.dataset.bookingPayment || '-';
        amount.textContent = trigger.dataset.bookingAmount || '-';
        balance.textContent = trigger.dataset.bookingBalance || 'Php 0.00';
        downpayment.textContent = trigger.dataset.bookingDownpayment || 'Php 0.00';
        reference.textContent = trigger.dataset.bookingReference || '-';
        meta.textContent = trigger.dataset.bookingStatusMeta || '-';
        status.textContent = trigger.dataset.bookingStatus || 'Pending';
        status.className = `member-booking-card__badge ${trigger.dataset.bookingStatusClass || ''}`.trim();
        const hasWifiVoucher = Boolean(trigger.dataset.bookingWifiCode);

        if (wifiWrap && wifiCode && wifiWindow && wifiMeta) {
            wifiWrap.classList.toggle('hidden', !hasWifiVoucher);
            wifiCode.textContent = hasWifiVoucher ? trigger.dataset.bookingWifiCode : '';
            wifiWindow.textContent = hasWifiVoucher ? trigger.dataset.bookingWifiWindow : '';
            wifiMeta.textContent = hasWifiVoucher ? trigger.dataset.bookingWifiMeta : '';
        }

        const canCancel = trigger.dataset.bookingCanCancel === '1';
        const canPayBalance = trigger.dataset.bookingCanPayBalance === '1';
        const canReschedule = trigger.dataset.bookingCanReschedule === '1';

        if (actions) {
            actions.classList.toggle('hidden', !canCancel && !canPayBalance && !canReschedule);
        }

        if (cancelForm) {
            cancelForm.classList.toggle('hidden', !canCancel);
        }

        if (cancelWarning) {
            cancelWarning.classList.toggle('hidden', !canCancel);
        }

        if (cancelForm) {
            if (canCancel) {
                cancelForm.setAttribute('action', trigger.dataset.bookingCancelUrl || '#');
            }
        }
        if (balanceLink) {
            balanceLink.classList.toggle('hidden', !canPayBalance);
            if (canPayBalance) {
                balanceLink.setAttribute('href', trigger.dataset.bookingBalanceUrl || '#');
            }
        }
        if (rescheduleLink) {
            rescheduleLink.classList.toggle('hidden', !canReschedule);
            if (canReschedule) {
                rescheduleLink.setAttribute('href', trigger.dataset.bookingRescheduleUrl || '#');
            }
        }

        modal.classList.remove('hidden');
        document.body.classList.add('modal-open');
    };

    triggers.forEach((trigger) => {
        trigger.addEventListener('click', () => {
            openModal(trigger);
        });
    });

    closers.forEach((closer) => {
        closer.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });
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
    const schedulePanel = form.querySelector('[data-booking-mode-panel="schedule"]');
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
    const paymentCash = form.querySelector('[data-payment-cash]');
    const paymentInstructions = form.querySelector('[data-payment-instructions]');
    const paymentProofWrap = form.querySelector('[data-payment-proof-wrap]');
    const paymentProofInput = form.querySelector('input[name="payment_proof"]');
    const isAdminMode = form.dataset.adminMode === 'true';
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
    const roomCardMap = new Map(
        roomCards.map((card) => [String(card.dataset.roomId || ''), card]),
    );
    const roomPreviewModal = document.querySelector('[data-room-preview-modal]');
    const roomPreviewTitle = document.querySelector('[data-room-preview-title]');
    const roomPreviewSpace = document.querySelector('[data-room-preview-space]');
    const roomPreviewDescription = document.querySelector('[data-room-preview-description]');
    const roomPreviewImage = document.querySelector('[data-room-preview-image]');
    const roomPreviewThumbs = document.querySelector('[data-room-preview-thumbs]');
    const roomPreviewTriggers = [...document.querySelectorAll('[data-room-preview-open]')];
    const roomPreviewClosers = [...document.querySelectorAll('[data-room-preview-close]')];

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

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');

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

    const buildRateBreakdownLabel = (data, startValue, endValue) => {
        const chargeLabel = String(data?.charge_period_label || '').trim();
        const rateName = String(data?.rate_name || '').trim();
        const suffix = chargeLabel ? ` - ${chargeLabel}` : '';
        const baseRateName = suffix && rateName.endsWith(suffix)
            ? rateName.slice(0, rateName.length - suffix.length)
            : rateName;

        if (chargeLabel === 'Day Daily Use') {
            return `${baseRateName}: day daily rate (8:00 AM - 8:00 PM)`;
        }

        if (chargeLabel === 'Night Daily Use') {
            return `${baseRateName}: night daily rate (8:00 PM - 8:00 AM)`;
        }

        if (chargeLabel === 'Day Daily Use + Extension') {
            const extension = durationLabel('20:00', endValue) || 'extra time';

            return `${baseRateName}: day daily rate + ${extension} extension after 8:00 PM`;
        }

        if (chargeLabel === 'Night Daily Use + Extension') {
            const extension = durationLabel('08:00', endValue) || 'extra time';

            return `${baseRateName}: night daily rate + ${extension} extension after 8:00 AM`;
        }

        if (chargeLabel === 'Day + Night Use') {
            return `${baseRateName}: combined day and night rate for the selected hours`;
        }

        if (chargeLabel === 'Day Use') {
            return `${baseRateName}: day use rate for the selected hours`;
        }

        if (chargeLabel === 'Night Use') {
            return `${baseRateName}: night use rate for the selected hours`;
        }

        return chargeLabel
            ? `${baseRateName}: ${chargeLabel}`
            : (baseRateName || '--');
    };

    const buildRateBreakdownMarkup = (data, startValue, endValue) => {
        const lines = Array.isArray(data?.breakdown) ? data.breakdown : [];

        if (lines.length) {
            return lines
                .filter((line) => line && typeof line.label === 'string')
                .map((line) => {
                    const amount = Number(line.amount || 0);

                    return `
                        <div class="flex items-start justify-between gap-3 text-right text-[0.92rem] font-medium text-[#4b623d]">
                            <span class="text-left text-[#6f7d72]">${escapeHtml(line.label)}</span>
                            <span class="shrink-0 text-[#173029]">${formatCurrency(amount)}</span>
                        </div>
                    `;
                })
                .join('');
        }

        return `<div class="text-[#4b623d]">${escapeHtml(buildRateBreakdownLabel(data, startValue, endValue))}</div>`;
    };

    const buildCompactBreakdownMarkup = (lines) => {
        if (!Array.isArray(lines) || !lines.length) {
            return '';
        }

        return `
            <div class="mt-2 space-y-1 text-[0.82rem] text-[#6f7d72]">
                ${lines
                    .filter((line) => line && typeof line.label === 'string')
                    .map((line) => `
                        <div class="flex items-start justify-between gap-3">
                            <span>${escapeHtml(line.label)}</span>
                            <strong class="shrink-0 font-semibold text-[#4b623d]">${formatCurrency(Number(line.amount || 0))}</strong>
                        </div>
                    `)
                    .join('')}
            </div>
        `;
    };

    const durationMinutesForRange = (startValue, endValue) => {
        if (!startValue || !endValue) {
            return 0;
        }

        const startMinutes = timeValueToMinutes(startValue);
        let endMinutes = timeValueToMinutes(endValue);

        if (startMinutes === null || endMinutes === null) {
            return 0;
        }

        if (endMinutes <= startMinutes) {
            endMinutes += 24 * 60;
        }

        return endMinutes - startMinutes;
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
        paymentCash?.classList.toggle('hidden', paymentMethod.value !== 'cash');

        if (paymentProofWrap && paymentProofInput) {
            const isCashWalkIn = isAdminMode && paymentMethod.value === 'cash';
            paymentProofWrap.classList.toggle('hidden', isCashWalkIn);
            paymentProofInput.required = !isCashWalkIn;
        }

        paymentMethodCards.forEach((card) => {
            card.classList.toggle('is-active', card.dataset.paymentChoice === paymentMethod.value);
        });
    };

    const showCheckout = () => {
        syncBookingEndDateForCurrentMode();
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
        if (bookingMode === 'monthly') {
            checkoutEndDateRow.classList.remove('hidden');
            checkoutEndDate.textContent = formatDate(bookingEndDateInput.value || bookingDateInput.value || todayValue);
            checkoutStart.textContent = currentQuote?.window_start_time ? formatTime(currentQuote.window_start_time) : (currentQuote?.long_stay_use_label || 'Monthly');
            checkoutEnd.textContent = currentQuote?.window_end_time ? formatTime(currentQuote.window_end_time) : (currentQuote?.long_stay_use_label || 'Monthly');
            checkoutDuration.textContent = currentQuote?.unit_label || '--';
            checkoutMonthlyPlanRow.classList.remove('hidden');
            checkoutMonthlyPlan.textContent = currentQuote?.long_stay_use_label
                ? `${monthlyPlanInput.value || '--'} • ${currentQuote.long_stay_use_label}`
                : (monthlyPlanInput.value || '--');
            return;
        }

        checkoutEndDateRow.classList.add('hidden');
        checkoutMonthlyPlanRow.classList.add('hidden');
        checkoutStart.textContent = startSelect.selectedOptions[0]?.textContent ?? startSelect.value ?? '--:--';
        checkoutEnd.textContent = endSelect.selectedOptions[0]?.textContent ?? endSelect.value ?? '--:--';
        checkoutDuration.textContent = durationLabel(startSelect.value, endSelect.value) || '--';
    };

    const normalizedDownpaymentValue = (commit = false) => {
        if (!currentQuote) {
            return 0;
        }

        const total = Number(currentQuote.total_amount || 0);
        const minimum = Number(currentQuote.minimum_downpayment_amount || 0);
        const rawValue = downpaymentInput.value.trim();

        if (!rawValue) {
            if (commit) {
                downpaymentInput.value = String(minimum);
            }

            return minimum;
        }

        let current = Number(rawValue);

        if (Number.isNaN(current)) {
            if (commit) {
                downpaymentInput.value = String(minimum);
            }

            return minimum;
        }

        current = Math.min(Math.max(current, minimum), total);

        if (commit) {
            downpaymentInput.value = String(current);
        }

        return current;
    };

    const updateBalance = (commit = false) => {
        if (!currentQuote) {
            quoteBalance.textContent = 'Php 0.00';
            checkoutSubmit.textContent = 'Confirm & Pay Php 0.00';
            return;
        }

        const total = Number(currentQuote.total_amount || 0);
        const current = normalizedDownpaymentValue(commit);

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
        durationDisplay.textContent = 'Choose a start time first to continue. Minimum booking is 2 hours.';
        messageBody.textContent = message;
        hideStartSummary();
        hideInlineSummary();
        updateCheckoutSummary();
    };

    const getSelectedRoomCard = () => roomCards.find((card) => card.dataset.roomId === roomSelect.value);

    const updateRoomMeta = () => {
        const roomCard = getSelectedRoomCard();

        if (!roomCard) {
            roomMeta.textContent = 'Choose a room first, then pick an available date and start time.';
            selectedRoomName.textContent = 'Choose a room';
            selectedRoomSpace.textContent = '';
            selectedRoomRate.textContent = 'Ask HYVE';
            monthlyRoomName.textContent = 'Choose a room';
            monthlyRoomSpace.textContent = '';
            monthlyRoomRate.textContent = 'Ask HYVE';
            checkoutRoom.textContent = 'Choose a room';
            return;
        }

        roomMeta.textContent = `${roomCard.dataset.roomDescription} | ${roomCard.dataset.roomSpace}`;
        selectedRoomName.textContent = roomCard.dataset.roomName || 'Choose a room';
        selectedRoomSpace.textContent = roomCard.dataset.roomSpace || '';
        selectedRoomRate.textContent = roomCard.dataset.roomRate || 'Ask HYVE';
        monthlyRoomName.textContent = roomCard.dataset.roomName || 'Choose a room';
        monthlyRoomSpace.textContent = roomCard.dataset.roomSpace || '';
        monthlyRoomRate.textContent = roomCard.dataset.roomRate || 'Ask HYVE';
        checkoutRoom.textContent = `${roomCard.dataset.roomName || 'Choose a room'} - ${roomCard.dataset.roomSpace || ''}`.trim();
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
            renderMonthlyCalendar();
            return;
        }

        const data = await fetchJson(`${unavailableDatesUrl}?hyve_room_id=${encodeURIComponent(roomSelect.value)}&horizon_days=${encodeURIComponent(horizonDays)}`);
        blockedDates = new Set((Array.isArray(data.unavailable_dates) ? data.unavailable_dates : []).map((item) => item.value));
        renderCalendar();
        renderMonthlyCalendar();
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
        durationDisplay.textContent = 'Choose a start time first to continue. Minimum booking is 2 hours.';
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
            durationDisplay.textContent = 'Choose a start time first to continue. Minimum booking is 2 hours.';
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
        summaryRate.innerHTML = buildRateBreakdownMarkup(data, startSelect.value, endSelect.value);
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

        card.addEventListener('keydown', async (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            if (event.target?.closest?.('[data-room-preview-open]')) {
                return;
            }

            event.preventDefault();
            card.click();
        });
    });

    roomPreviewTriggers.forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            const roomCard = trigger.closest('[data-room-card]');

            if (roomCard?.dataset.roomId) {
                setActiveRoom(roomCard.dataset.roomId);
            }

            openRoomPreview();
        });
    });

    roomPreviewClosers.forEach((closer) => {
        closer.addEventListener('click', closeRoomPreview);
    });

    roomPreviewModal?.addEventListener('click', (event) => {
        if (event.target === roomPreviewModal) {
            closeRoomPreview();
        }
    });

    paymentMethodCards.forEach((card) => {
        card.addEventListener('click', () => {
            paymentMethod.value = card.dataset.paymentChoice || '';
            updatePaymentDestination();
        });
    });
    paymentMethod.addEventListener('change', updatePaymentDestination);
    downpaymentInput.addEventListener('input', () => updateBalance(false));
    downpaymentInput.addEventListener('blur', () => updateBalance(true));
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

    monthlyStartDateInput.addEventListener('change', async () => {
        bookingDateInput.value = monthlyStartDateInput.value || '';
        monthlyEndDateInput.min = monthlyStartDateInput.value || todayValue;

        if (!monthlyStartDateInput.value) {
            bookingEndDateInput.value = '';
            monthlyEndDateInput.value = '';
            monthlyRangeAnchor = '';
            monthlySelectingEnd = false;
            await refreshMonthlySelection();
            return;
        }

        if (blockedDates.has(monthlyStartDateInput.value)) {
            monthlyStartDateInput.value = '';
            monthlyEndDateInput.value = '';
            bookingDateInput.value = '';
            bookingEndDateInput.value = '';
            monthlyRangeAnchor = '';
            monthlySelectingEnd = false;
            monthlyPlanDescription.textContent = 'That start date is already booked for this room. Please choose another available day.';
            renderMonthlyCalendar();
            await refreshMonthlySelection();
            return;
        }

        if (!monthlyEndDateInput.value || monthlyEndDateInput.value < monthlyStartDateInput.value) {
            monthlyEndDateInput.value = monthlyStartDateInput.value;
            bookingEndDateInput.value = monthlyStartDateInput.value;
            monthlySelectingEnd = true;
        } else {
            bookingEndDateInput.value = monthlyEndDateInput.value;
            monthlySelectingEnd = false;
        }

        monthlyRangeAnchor = monthlyStartDateInput.value;
        monthlyCalendarMonth = new Date(`${monthlyStartDateInput.value}T00:00:00`);
        renderMonthlyCalendar();
        await refreshMonthlySelection();
    });

    monthlyEndDateInput.addEventListener('change', async () => {
        bookingEndDateInput.value = monthlyEndDateInput.value || '';

        if (!monthlyEndDateInput.value) {
            monthlySelectingEnd = true;
            await refreshMonthlySelection();
            return;
        }

        if (monthlyStartDateInput.value && monthlyEndDateInput.value < monthlyStartDateInput.value) {
            monthlyEndDateInput.value = monthlyStartDateInput.value;
            bookingEndDateInput.value = monthlyStartDateInput.value;
        }

        if (blockedDates.has(monthlyEndDateInput.value) || hasBlockedDatesInRange(monthlyStartDateInput.value || monthlyEndDateInput.value, monthlyEndDateInput.value)) {
            monthlyEndDateInput.value = monthlyStartDateInput.value || '';
            bookingEndDateInput.value = monthlyStartDateInput.value || '';
            monthlySelectingEnd = true;
            monthlyPlanDescription.textContent = 'That stay period includes one or more booked dates. Please choose another end date.';
            renderMonthlyCalendar();
            await refreshMonthlySelection();
            return;
        }

        monthlySelectingEnd = false;
        renderMonthlyCalendar();
        await refreshMonthlySelection();
    });

    bookingDateInput.addEventListener('change', () => {
        syncBookingEndDateForCurrentMode();
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
    const sharedRoomStrip = form.querySelector('[data-shared-room-strip]');
    const roomSelect = form.querySelector('[data-room-select]');
    const bookingDateInput = form.querySelector('[data-booking-date]');
    const bookingEndDateInput = form.querySelector('[data-booking-end-date]');
    const bookingModeInput = form.querySelector('[data-booking-mode-input]');
    const monthlyPlanInput = form.querySelector('[data-monthly-plan-input]');
    const scheduleItemsInput = form.querySelector('[data-schedule-items-input]');
    const startSelect = form.querySelector('[data-start-time-select]');
    const endSelect = form.querySelector('[data-end-time-select]');
    const bookingPicker = form.querySelector('[data-booking-picker]');
    const bookingCheckout = form.querySelector('[data-booking-checkout]');
    const schedulePanel = form.querySelector('[data-booking-mode-panel="schedule"]');
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
    const paymentCash = form.querySelector('[data-payment-cash]');
    const paymentInstructions = form.querySelector('[data-payment-instructions]');
    const paymentProofWrap = form.querySelector('[data-payment-proof-wrap]');
    const paymentProofInput = form.querySelector('input[name="payment_proof"]');
    const isAdminMode = form.dataset.adminMode === 'true';
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
    const checkoutEndDateRow = form.querySelector('[data-checkout-end-date-row]');
    const checkoutEndDate = form.querySelector('[data-checkout-end-date]');
    const checkoutStart = form.querySelector('[data-checkout-start]');
    const checkoutEnd = form.querySelector('[data-checkout-end]');
    const checkoutDuration = form.querySelector('[data-checkout-duration]');
    const checkoutMonthlyPlanRow = form.querySelector('[data-checkout-monthly-plan-row]');
    const checkoutMonthlyPlan = form.querySelector('[data-checkout-monthly-plan]');
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
    const scheduleCartHeading = form.querySelector('[data-schedule-cart-heading]');
    const scheduleCartEmpty = form.querySelector('[data-schedule-cart-empty]');
    const scheduleCartTotal = form.querySelector('[data-schedule-cart-total]');
    const monthlyStartDateInput = form.querySelector('[data-monthly-start-date]');
    const monthlyEndDateInput = form.querySelector('[data-monthly-end-date]');
    const monthlyCalendarTitle = form.querySelector('[data-monthly-calendar-title]');
    const monthlyCalendarDays = form.querySelector('[data-monthly-calendar-days]');
    const monthlyCalendarPrev = form.querySelector('[data-monthly-calendar-prev]');
    const monthlyCalendarNext = form.querySelector('[data-monthly-calendar-next]');
    const monthlyBlockedOpen = form.querySelector('[data-monthly-blocked-open]');
    const monthlyBlockedNote = form.querySelector('[data-monthly-blocked-note]');
    const monthlyPlanDescription = form.querySelector('[data-monthly-plan-description]');
    const longStayUseTypeInput = form.querySelector('[data-long-stay-use-type-input]');
    const longStayUseWrap = form.querySelector('[data-long-stay-use-wrap]');
    const longStayUseChoices = [...form.querySelectorAll('[data-long-stay-use-choice]')];
    const monthlyRoomName = form.querySelector('[data-monthly-room-name]');
    const monthlyRoomSpace = form.querySelector('[data-monthly-room-space]');
    const monthlyRoomRate = form.querySelector('[data-monthly-room-rate]');
    const monthlyInlineSummary = form.querySelector('[data-monthly-inline-summary]');
    const monthlySummaryDate = form.querySelector('[data-monthly-summary-date]');
    const monthlySummaryEndDate = form.querySelector('[data-monthly-summary-end-date]');
    const monthlySummaryPlan = form.querySelector('[data-monthly-summary-plan]');
    const monthlySummaryUseTypeRow = form.querySelector('[data-monthly-summary-use-type-row]');
    const monthlySummaryUseType = form.querySelector('[data-monthly-summary-use-type]');
    const monthlySummaryUnits = form.querySelector('[data-monthly-summary-units]');
    const monthlySummaryTotal = form.querySelector('[data-monthly-summary-total]');
    const monthlyContinue = form.querySelector('[data-monthly-continue]');
    const monthlyBlockedModal = document.querySelector('[data-monthly-blocked-modal]');
    const monthlyBlockedTitle = document.querySelector('[data-monthly-blocked-title]');
    const monthlyBlockedSubtitle = document.querySelector('[data-monthly-blocked-subtitle]');
    const monthlyBlockedCount = document.querySelector('[data-monthly-blocked-count]');
    const monthlyBlockedEmpty = document.querySelector('[data-monthly-blocked-empty]');
    const monthlyBlockedList = document.querySelector('[data-monthly-blocked-list]');
    const monthlyBlockedCalendarTitle = document.querySelector('[data-monthly-blocked-calendar-title]');
    const monthlyBlockedCalendarDays = document.querySelector('[data-monthly-blocked-calendar-days]');
    const monthlyBlockedPrev = document.querySelector('[data-monthly-blocked-prev]');
    const monthlyBlockedNext = document.querySelector('[data-monthly-blocked-next]');
    const monthlyBlockedClosers = [...document.querySelectorAll('[data-monthly-blocked-close]')];
    const roomPreviewModal = document.querySelector('[data-room-preview-modal]');
    const roomPreviewTitle = document.querySelector('[data-room-preview-title]');
    const roomPreviewSpace = document.querySelector('[data-room-preview-space]');
    const roomPreviewDescription = document.querySelector('[data-room-preview-description]');
    const roomPreviewImage = document.querySelector('[data-room-preview-image]');
    const roomPreviewThumbs = document.querySelector('[data-room-preview-thumbs]');
    const roomPreviewTriggers = [...document.querySelectorAll('[data-room-preview-open]')];
    const roomPreviewClosers = [...document.querySelectorAll('[data-room-preview-close]')];
    const shouldShowCheckout = form.dataset.showCheckout === 'true';
    const initialStartTime = startSelect.value;
    const initialEndTime = endSelect.value;
    const initialMonthlyPlan = monthlyPlanInput?.value || '';

    if (!roomSelect || !bookingDateInput || !bookingEndDateInput || !bookingModeInput || !monthlyPlanInput || !longStayUseTypeInput || !scheduleItemsInput || !startSelect || !endSelect || !bookingPicker || !bookingCheckout || !checkoutBack || !durationDisplay || !downpaymentInput || !paymentMethod || !paymentMethodCards.length || !roomMeta || !messageBody || !quoteTotal || !quoteMinimum || !quoteBalance || !quoteMeta || !paymentGcash || !paymentBank || !paymentInstructions || !roomCards.length || !roomRail || !calendarTitle || !calendarDays || !calendarPrev || !calendarNext || !slotDateTitle || !slotContinue || !selectedRoomName || !selectedRoomSpace || !selectedRoomRate || !startSlots || !endSlots || !startStep || !startSummary || !startSummaryTime || !startSummaryChange || !inlineSummary || !summaryDate || !summaryStart || !summaryEnd || !summaryDuration || !summaryRate || !summaryTotal || !checkoutRoom || !checkoutDate || !checkoutEndDateRow || !checkoutEndDate || !checkoutStart || !checkoutEnd || !checkoutDuration || !checkoutMonthlyPlanRow || !checkoutMonthlyPlan || !checkoutStandardSummary || !checkoutScheduleCount || !checkoutScheduleList || !checkoutSubmit || !scheduleDateTitle || !schedulePrev || !scheduleNext || !scheduleHead || !scheduleBody || !scheduleSelectionEmpty || !scheduleSelectionFilled || !scheduleSelectionRoom || !scheduleSelectionMeta || !scheduleSelectionTotal || !scheduleContinue || !scheduleCartPanel || !scheduleCartList || !scheduleCartCount || !scheduleCartHeading || !scheduleCartEmpty || !scheduleCartTotal || !monthlyStartDateInput || !monthlyEndDateInput || !monthlyCalendarTitle || !monthlyCalendarDays || !monthlyCalendarPrev || !monthlyCalendarNext || !monthlyBlockedOpen || !monthlyBlockedNote || !monthlyPlanDescription || !longStayUseWrap || !monthlyRoomName || !monthlyRoomSpace || !monthlyRoomRate || !monthlyInlineSummary || !monthlySummaryDate || !monthlySummaryEndDate || !monthlySummaryPlan || !monthlySummaryUseTypeRow || !monthlySummaryUseType || !monthlySummaryUnits || !monthlySummaryTotal || !monthlyContinue || !monthlyBlockedModal || !monthlyBlockedTitle || !monthlyBlockedSubtitle || !monthlyBlockedCount || !monthlyBlockedEmpty || !monthlyBlockedList || !monthlyBlockedCalendarTitle || !monthlyBlockedCalendarDays || !monthlyBlockedPrev || !monthlyBlockedNext) {
        return;
    }

    let blockedDates = new Set();
    let currentQuote = null;
    let bookingMode = bookingModeInput.value || 'room';
    let scheduleCart = [];
    let monthlyRangeAnchor = bookingDateInput.value || '';
    let monthlySelectingEnd = bookingDateInput.value === bookingEndDateInput.value;
    let monthlyManualPlanType = '';
    let monthlyBlockedCalendarMonth = (() => {
        const base = bookingDateInput.value ? new Date(`${bookingDateInput.value}T00:00:00`) : new Date();
        return new Date(base.getFullYear(), base.getMonth(), 1);
    })();
    let currentMonth = (() => {
        const base = bookingDateInput.value ? new Date(`${bookingDateInput.value}T00:00:00`) : new Date();
        return new Date(base.getFullYear(), base.getMonth(), 1);
    })();
    let monthlyCalendarMonth = (() => {
        const base = bookingDateInput.value ? new Date(`${bookingDateInput.value}T00:00:00`) : new Date();
        return new Date(base.getFullYear(), base.getMonth(), 1);
    })();

    const today = new Date();
    const todayValue = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
    const currentMinutes = (today.getHours() * 60) + today.getMinutes();
    const roomCardMap = new Map(
        roomCards.map((card) => [String(card.dataset.roomId || ''), card]),
    );

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

    const selectedLongStayRange = () => {
        const startValue = monthlyStartDateInput.value || bookingDateInput.value || '';
        const endValue = monthlyEndDateInput.value || bookingEndDateInput.value || startValue;

        return { startValue, endValue };
    };

    const longStayDayCount = () => {
        const { startValue, endValue } = selectedLongStayRange();

        if (!startValue || !endValue) {
            return 0;
        }

        const start = new Date(`${startValue}T00:00:00`);
        const end = new Date(`${endValue}T00:00:00`);

        if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end < start) {
            return 0;
        }

        return Math.floor((end.getTime() - start.getTime()) / 86400000) + 1;
    };

    const recommendedLongStayType = () => {
        const days = longStayDayCount();

        if (days >= 30) {
            return 'monthly';
        }

        if (days >= 7) {
            return 'weekly';
        }

        return 'daily';
    };

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

    const durationMinutesForRange = (startValue, endValue) => {
        if (!startValue || !endValue) {
            return 0;
        }

        const startMinutes = timeValueToMinutes(startValue);
        let endMinutes = timeValueToMinutes(endValue);

        if (startMinutes === null || endMinutes === null) {
            return 0;
        }

        if (endMinutes <= startMinutes) {
            endMinutes += 24 * 60;
        }

        return endMinutes - startMinutes;
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
            breakdown: Array.isArray(item.breakdown) ? item.breakdown : [],
        })));
    };

    const summarizeScheduleCart = () => {
        const total = scheduleCart.reduce((sum, item) => sum + Number(item.total_amount || 0), 0);
        const roomCount = new Set(scheduleCart.map((item) => item.hyve_room_id)).size;
        const dateCount = new Set(scheduleCart.map((item) => item.booking_date)).size;
        const totalMinutes = scheduleCart.reduce(
            (sum, item) => sum + durationMinutesForRange(item.start_time, item.end_time),
            0,
        );
        return {
            total,
            roomCount,
            dateCount,
            slotCount: scheduleCart.length,
            totalMinutes,
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
                    const roomCard = roomCardMap.get(String(item.hyve_room_id));

                    return {
                        hyve_room_id: Number(item.hyve_room_id),
                        booking_date: item.booking_date,
                        start_time: item.start_time,
                        end_time: item.end_time,
                        room_name: item.room_name || roomCard?.dataset.roomName || 'Room',
                        room_space: item.room_space || roomCard?.dataset.roomSpace || '',
                        label: item.label || `${item.start_time} - ${item.end_time}`,
                        total_amount: Number(item.total_amount || 0),
                        breakdown: Array.isArray(item.breakdown) ? item.breakdown : [],
                    };
                });
            syncScheduleItemsInput();
        } catch (error) {
            scheduleCart = [];
        }
    };

    const getSelectedRoomCard = () => roomCardMap.get(roomSelect.value);

    const closeRoomPreview = () => {
        if (!roomPreviewModal) {
            return;
        }

        roomPreviewModal.classList.add('hidden');
        document.body.classList.remove('modal-open');
    };

    const openRoomPreview = () => {
        const roomCard = getSelectedRoomCard();

        if (!roomCard || !roomPreviewModal || !roomPreviewTitle || !roomPreviewSpace || !roomPreviewDescription || !roomPreviewImage || !roomPreviewThumbs) {
            return;
        }

        const gallery = (() => {
            try {
                const parsed = JSON.parse(roomCard.dataset.roomGallery || '[]');
                return Array.isArray(parsed) ? parsed : [];
            } catch (error) {
                return [];
            }
        })();
        const images = gallery.length ? gallery : [roomPreviewImage.getAttribute('src') || ''];

        roomPreviewTitle.textContent = roomCard.dataset.roomName || 'Room preview';
        roomPreviewSpace.textContent = roomCard.dataset.roomSpace || '';
        roomPreviewDescription.textContent = roomCard.dataset.roomDescription || 'See the selected room before continuing your booking.';
        roomPreviewImage.setAttribute('src', images[0] || '');
        roomPreviewImage.setAttribute('alt', roomCard.dataset.roomName || 'Room preview');
        roomPreviewThumbs.innerHTML = '';

        images.forEach((image, index) => {
            const thumb = document.createElement('button');
            thumb.type = 'button';
            thumb.className = 'booking-room-preview-modal__thumb';
            thumb.classList.toggle('is-active', index === 0);

            const img = document.createElement('img');
            img.src = image;
            img.alt = `${roomCard.dataset.roomName || 'Room'} preview ${index + 1}`;
            thumb.append(img);
            thumb.addEventListener('click', () => {
                roomPreviewImage.setAttribute('src', image);
                roomPreviewThumbs.querySelectorAll('.booking-room-preview-modal__thumb').forEach((item, itemIndex) => {
                    item.classList.toggle('is-active', itemIndex === index);
                });
            });
            roomPreviewThumbs.append(thumb);
        });

        roomPreviewModal.classList.remove('hidden');
        document.body.classList.add('modal-open');
    };

    const getMonthlyOptionsForRoom = (roomId) => {
        const roomCard = roomCardMap.get(String(roomId));
        const source = roomCard?.dataset.roomMonthlyOptions || '[]';

        try {
            const parsed = JSON.parse(source);
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    };

    const setBookingMode = (mode) => {
        bookingMode = mode;
        bookingModeInput.value = mode;
        sharedRoomStrip?.classList.toggle('hidden', mode === 'schedule');
        modeTriggers.forEach((trigger) => {
            trigger.classList.toggle('booking-calendar-tab--active', trigger.dataset.bookingModeValue === mode);
        });
        modePanels.forEach((panel) => {
            panel.classList.toggle('hidden', panel.dataset.bookingModePanel !== mode);
        });
    };

    const syncBookingEndDateForCurrentMode = () => {
        if (bookingMode === 'monthly') {
            bookingEndDateInput.value = monthlyEndDateInput.value || bookingEndDateInput.value || bookingDateInput.value || '';
            return;
        }

        bookingEndDateInput.value = bookingDateInput.value || '';
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
        paymentCash?.classList.toggle('hidden', paymentMethod.value !== 'cash');

        if (paymentProofWrap && paymentProofInput) {
            const isCashWalkIn = isAdminMode && paymentMethod.value === 'cash';
            paymentProofWrap.classList.toggle('hidden', isCashWalkIn);
            paymentProofInput.required = !isCashWalkIn;
        }

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
        if ((isScheduleModeActive() || bookingModeInput.value === 'schedule') && scheduleCart.length) {
            const summary = summarizeScheduleCart();
            checkoutEndDateRow.classList.add('hidden');
            checkoutMonthlyPlanRow.classList.add('hidden');
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
                        <span>${formatDate(item.booking_date)} - ${item.label} - ${durationLabel(item.start_time, item.end_time) || '2 hours'}</span>
                        ${buildCompactBreakdownMarkup(item.breakdown)}
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
        if (bookingMode === 'monthly') {
            checkoutEndDateRow.classList.remove('hidden');
            checkoutEndDate.textContent = formatDate(bookingEndDateInput.value || bookingDateInput.value || todayValue);
            checkoutStart.textContent = 'Monthly';
            checkoutEnd.textContent = 'Monthly';
            checkoutDuration.textContent = currentQuote?.unit_label || '--';
            checkoutMonthlyPlanRow.classList.remove('hidden');
            checkoutMonthlyPlan.textContent = monthlyPlanInput.value || '--';
            return;
        }

        checkoutEndDateRow.classList.add('hidden');
        checkoutMonthlyPlanRow.classList.add('hidden');
        checkoutStart.textContent = startSelect.selectedOptions[0]?.textContent ?? startSelect.value ?? '--:--';
        checkoutEnd.textContent = endSelect.selectedOptions[0]?.textContent ?? endSelect.value ?? '--:--';
        checkoutDuration.textContent = durationLabel(startSelect.value, endSelect.value) || '--';
    };

    const normalizedDownpaymentValue = (commit = false) => {
        if (!currentQuote) {
            return 0;
        }

        const total = Number(currentQuote.total_amount || 0);
        const minimum = Number(currentQuote.minimum_downpayment_amount || 0);
        const rawValue = downpaymentInput.value.trim();

        if (!rawValue) {
            if (commit) {
                downpaymentInput.value = String(minimum);
            }

            return minimum;
        }

        let current = Number(rawValue);

        if (Number.isNaN(current)) {
            if (commit) {
                downpaymentInput.value = String(minimum);
            }

            return minimum;
        }

        current = Math.min(Math.max(current, minimum), total);

        if (commit) {
            downpaymentInput.value = String(current);
        }

        return current;
    };

    const updateBalance = (commit = false) => {
        if (!currentQuote) {
            quoteBalance.textContent = 'Php 0.00';
            checkoutSubmit.textContent = 'Confirm & Pay Php 0.00';
            return;
        }

        const total = Number(currentQuote.total_amount || 0);
        const current = normalizedDownpaymentValue(commit);

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

    const resetMonthlySummary = () => {
        monthlyInlineSummary.classList.add('hidden');
        monthlySummaryDate.textContent = formatDate(bookingDateInput.value || todayValue);
        monthlySummaryEndDate.textContent = formatDate(bookingEndDateInput.value || bookingDateInput.value || todayValue);
        monthlySummaryPlan.textContent = '--';
        monthlySummaryUseType.textContent = '--';
        monthlySummaryUseTypeRow.classList.add('hidden');
        monthlySummaryUnits.textContent = '--';
        monthlySummaryTotal.textContent = 'Php 0.00';
        monthlyContinue.disabled = true;
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

    const syncScheduleSlotVisual = (button, selected) => {
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        button.classList.toggle('is-selected', selected);
        button.classList.remove('is-pending');
        button.setAttribute('aria-pressed', selected ? 'true' : 'false');

        const dot = button.querySelector('.booking-schedule__slot-dot');
        const price = button.querySelector('.booking-schedule__slot-price');

        if (dot) {
            dot.innerHTML = selected ? '&#10003;' : '';
        }

        if (price) {
            price.textContent = button.dataset.roomRateLabel || 'Available';
        }
    };

    const refreshScheduleGridSelections = () => {
        const selectedKeys = new Set(scheduleCart.map((item) => scheduleItemKey(item)));

        scheduleBody.querySelectorAll('[data-schedule-item-key]').forEach((button) => {
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            syncScheduleSlotVisual(button, selectedKeys.has(button.dataset.scheduleItemKey || ''));
        });
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
        const summary = summarizeScheduleCart();
        scheduleCartList.innerHTML = '';
        scheduleCartCount.textContent = `${scheduleCart.length} item${scheduleCart.length === 1 ? '' : 's'}`;
        scheduleCartHeading.textContent = `Your bookings (${scheduleCart.length})`;
        scheduleCartEmpty.classList.toggle('hidden', scheduleCart.length !== 0);
        scheduleCartList.classList.toggle('hidden', scheduleCart.length === 0);
        scheduleCartTotal.textContent = formatCurrency(summary.total);

        if (!scheduleCart.length) {
            refreshScheduleGridSelections();
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
                        <span>${formatDate(item.booking_date)} - ${item.label} - ${durationLabel(item.start_time, item.end_time) || '2 hours'}</span>
                        ${buildCompactBreakdownMarkup(item.breakdown)}
                    </div>
                    <div class="booking-schedule__cart-meta">
                        <strong>${formatCurrency(item.total_amount)}</strong>
                        <button type="button" class="booking-schedule__cart-remove" aria-label="Remove schedule item">&times;</button>
                    </div>
                `;

                row.querySelector('button')?.addEventListener('click', async () => {
                    const selectedButton = scheduleBody.querySelector(`[data-schedule-item-key="${scheduleItemKey(item)}"]`);
                    removeScheduleItem(scheduleItemKey(item));
                    syncScheduleSlotVisual(selectedButton, false);

                    if (bookingMode === 'schedule') {
                        updateScheduleSelection();
                        updateCheckoutSummary();
                    }
                });

                scheduleCartList.append(row);
            });

        refreshScheduleGridSelections();
    };

    const revealScheduleCart = () => {
        scheduleCartEmpty.classList.add('hidden');
        scheduleCartList.classList.remove('hidden');

        if (typeof scheduleCartPanel.scrollIntoView === 'function') {
            scheduleCartPanel.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest',
            });
        }
    };

    const isScheduleModeActive = () => {
        if (bookingMode === 'schedule' || bookingModeInput.value === 'schedule') {
            return true;
        }

        return Boolean(schedulePanel && !schedulePanel.classList.contains('hidden'));
    };

    const applyScheduleSummaryDisplay = () => {
        if (!scheduleCart.length) {
            scheduleSelectionEmpty.classList.remove('hidden');
            scheduleSelectionFilled.classList.add('hidden');
            scheduleSelectionRoom.textContent = '0 slots selected';
            scheduleSelectionMeta.textContent = 'Pick at least 2 hours of available slots to continue.';
            scheduleSelectionTotal.textContent = 'Php 0.00';
            scheduleCartTotal.textContent = 'Php 0.00';
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

        bookingModeInput.value = 'schedule';
        bookingMode = 'schedule';
        scheduleSelectionEmpty.classList.add('hidden');
        scheduleSelectionFilled.classList.remove('hidden');
        scheduleSelectionRoom.textContent = `${summary.slotCount} selected slot${summary.slotCount === 1 ? '' : 's'} across ${summary.roomCount} room${summary.roomCount === 1 ? '' : 's'}`;
        scheduleSelectionMeta.textContent = summary.dateCount === 1
            ? `Booking date: ${formatDate(scheduleCart[0].booking_date)}`
            : `${summary.dateCount} booking dates selected`;
        scheduleSelectionTotal.textContent = formatCurrency(summary.total);
        scheduleCartTotal.textContent = formatCurrency(summary.total);
        quoteTotal.textContent = formatCurrency(summary.total);
        quoteMinimum.textContent = formatCurrency(summary.minimumDownpayment);
        quoteMeta.textContent = `Full schedule cart | ${summary.slotCount} hour slot(s) | ${summary.roomCount} room(s).`;

        if (!downpaymentInput.value || Number(downpaymentInput.value) < summary.minimumDownpayment) {
            downpaymentInput.value = String(summary.minimumDownpayment);
        }

        downpaymentInput.min = String(summary.minimumDownpayment);
        updateBalance();

        if (summary.totalMinutes < minimumDuration) {
            const missingHours = (minimumDuration - summary.totalMinutes) / 60;
            scheduleSelectionMeta.textContent = `Add ${missingHours} more hour${missingHours === 1 ? '' : 's'} to reach the 2-hour minimum booking.`;
            scheduleContinue.disabled = true;
            return;
        }

        scheduleContinue.disabled = false;
    };

    const updateScheduleSelection = () => {
        syncScheduleItemsInput();
        renderScheduleCart();
        refreshScheduleGridSelections();

        if (!isScheduleModeActive() && !scheduleCart.length && bookingModeInput.value !== 'schedule') {
            return;
        }

        applyScheduleSummaryDisplay();
    };

    const parseDateOnlyValue = (value) => {
        if (!value || typeof value !== 'string') {
            return null;
        }

        const match = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);

        if (!match) {
            return null;
        }

        const year = Number(match[1]);
        const month = Number(match[2]);
        const day = Number(match[3]);
        const date = new Date(year, month - 1, day);

        if (
            Number.isNaN(date.getTime())
            || date.getFullYear() !== year
            || (date.getMonth() + 1) !== month
            || date.getDate() !== day
        ) {
            return null;
        }

        return date;
    };

    const isWholeCalendarMonthStay = (startValue, endValue) => {
        const start = parseDateOnlyValue(startValue);
        const end = parseDateOnlyValue(endValue);

        if (!start || !end || end < start) {
            return false;
        }

        const cursor = new Date(start.getFullYear(), start.getMonth(), start.getDate());

        while (cursor <= end) {
            const monthStart = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
            const monthEnd = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0);

            if (cursor.getTime() === start.getTime()) {
                if (cursor.getDate() !== monthStart.getDate()) {
                    return false;
                }
            }

            if (monthEnd > end) {
                return monthEnd.getTime() === end.getTime();
            }

            cursor.setMonth(cursor.getMonth() + 1, 1);
        }

        return false;
    };

    const hasBlockedDatesInRange = (startValue, endValue) => {
        if (!startValue || !endValue) {
            return false;
        }

        const start = parseDateOnlyValue(startValue);
        const end = parseDateOnlyValue(endValue);

        if (!start || !end || end < start) {
            return false;
        }

        const cursor = new Date(start);

        while (cursor <= end) {
            const value = `${cursor.getFullYear()}-${String(cursor.getMonth() + 1).padStart(2, '0')}-${String(cursor.getDate()).padStart(2, '0')}`;

            if (blockedDates.has(value)) {
                return true;
            }

            cursor.setDate(cursor.getDate() + 1);
        }

        return false;
    };

    const blockedDatesWithinRange = (startValue, endValue) => {
        if (!startValue || !endValue) {
            return [];
        }

        const start = new Date(`${startValue}T00:00:00`);
        const end = new Date(`${endValue}T00:00:00`);

        if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end < start) {
            return [];
        }

        const matches = [];
        const cursor = new Date(start);

        while (cursor <= end) {
            const value = `${cursor.getFullYear()}-${String(cursor.getMonth() + 1).padStart(2, '0')}-${String(cursor.getDate()).padStart(2, '0')}`;

            if (blockedDates.has(value)) {
                matches.push(value);
            }

            cursor.setDate(cursor.getDate() + 1);
        }

        return matches;
    };

    const updateMonthlyBlockedNote = () => {
        if (!roomSelect.value) {
            monthlyBlockedOpen.disabled = true;
            monthlyBlockedNote.textContent = 'Select a room first so HYVE can check which stay dates are already booked.';
            monthlyStartDateInput.setCustomValidity('');
            monthlyEndDateInput.setCustomValidity('');
            return;
        }

        monthlyBlockedOpen.disabled = false;

        if (!blockedDates.size) {
            monthlyBlockedNote.textContent = 'No blocked stay dates found yet for this room within the current booking window.';
            monthlyStartDateInput.setCustomValidity('');
            monthlyEndDateInput.setCustomValidity('');
            return;
        }

        const selectedBlockedDates = blockedDatesWithinRange(
            monthlyStartDateInput.value || bookingDateInput.value,
            monthlyEndDateInput.value || bookingEndDateInput.value || monthlyStartDateInput.value || bookingDateInput.value,
        );

        if (selectedBlockedDates.length) {
            const preview = selectedBlockedDates.slice(0, 4).map((value) => formatDate(value)).join(', ');
            const suffix = selectedBlockedDates.length > 4 ? ` and ${selectedBlockedDates.length - 4} more` : '';
            const message = `This room is already booked on: ${preview}${suffix}. Please choose another stay period.`;
            monthlyBlockedNote.textContent = message;
            monthlyStartDateInput.setCustomValidity(message);
            monthlyEndDateInput.setCustomValidity(message);
            return;
        }

        const upcomingBlockedDates = [...blockedDates]
            .filter((value) => value >= (monthlyStartDateInput.min || todayValue))
            .slice(0, 4)
            .map((value) => formatDate(value));

        monthlyStartDateInput.setCustomValidity('');
        monthlyEndDateInput.setCustomValidity('');
        monthlyBlockedNote.textContent = upcomingBlockedDates.length
            ? `Booked dates for this room include: ${upcomingBlockedDates.join(', ')}.`
            : 'This room is open for long-stay booking on the dates currently loaded.';
    };

    const closeMonthlyBlockedModal = () => {
        monthlyBlockedModal.classList.add('hidden');
        document.body.classList.remove('modal-open');
    };

    const renderMonthlyBlockedCalendar = () => {
        monthlyBlockedCalendarTitle.textContent = formatMonthTitle(monthlyBlockedCalendarMonth);
        monthlyBlockedCalendarDays.innerHTML = '';

        const firstDay = new Date(monthlyBlockedCalendarMonth.getFullYear(), monthlyBlockedCalendarMonth.getMonth(), 1);
        const lastDay = new Date(monthlyBlockedCalendarMonth.getFullYear(), monthlyBlockedCalendarMonth.getMonth() + 1, 0);
        const leading = firstDay.getDay();
        const selectedStart = monthlyStartDateInput.value || bookingDateInput.value || '';
        const selectedEnd = monthlyEndDateInput.value || bookingEndDateInput.value || selectedStart;

        for (let i = 0; i < leading; i += 1) {
            const filler = document.createElement('span');
            filler.className = 'booking-calendar-day is-filler';
            monthlyBlockedCalendarDays.append(filler);
        }

        for (let day = 1; day <= lastDay.getDate(); day += 1) {
            const date = new Date(monthlyBlockedCalendarMonth.getFullYear(), monthlyBlockedCalendarMonth.getMonth(), day);
            const value = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'booking-calendar-day';
            button.textContent = String(day);
            button.disabled = true;

            if (value === todayValue) {
                button.classList.add('is-today');
            }

            if (selectedStart && selectedEnd && value >= selectedStart && value <= selectedEnd) {
                button.classList.add('is-selected');
            }

            if (value === selectedStart || value === selectedEnd) {
                button.classList.add('is-range-edge');
            }

            if (blockedDates.has(value)) {
                button.classList.add('is-booked');
            }

            monthlyBlockedCalendarDays.append(button);
        }
    };

    const renderMonthlyBlockedModal = () => {
        const roomCard = getSelectedRoomCard();
        const blockedDateValues = [...blockedDates].sort((a, b) => a.localeCompare(b));

        monthlyBlockedTitle.textContent = roomCard
            ? `${roomCard.dataset.roomName || 'Room'} blocked dates`
            : 'Select a room first';
        monthlyBlockedSubtitle.textContent = roomCard
            ? `${roomCard.dataset.roomSpace || 'Workspace'} long-stay dates already reserved by another booking.`
            : 'Blocked dates for long-stay booking will appear here.';
        monthlyBlockedCount.textContent = `${blockedDateValues.length} date${blockedDateValues.length === 1 ? '' : 's'}`;
        monthlyBlockedList.innerHTML = '';

        if (!roomCard) {
            monthlyBlockedEmpty.textContent = 'Select a room first so HYVE can load its blocked dates.';
            monthlyBlockedEmpty.classList.remove('hidden');
            monthlyBlockedList.classList.add('hidden');
            renderMonthlyBlockedCalendar();
            return;
        }

        if (!blockedDateValues.length) {
            monthlyBlockedEmpty.textContent = 'No blocked dates found for this room within the current booking window.';
            monthlyBlockedEmpty.classList.remove('hidden');
            monthlyBlockedList.classList.add('hidden');
            renderMonthlyBlockedCalendar();
            return;
        }

        monthlyBlockedEmpty.classList.add('hidden');
        monthlyBlockedList.classList.remove('hidden');
        blockedDateValues.forEach((value) => {
            const chip = document.createElement('span');
            chip.className = 'booking-availability-modal__date';
            chip.textContent = formatDate(value);
            monthlyBlockedList.append(chip);
        });
        renderMonthlyBlockedCalendar();
    };

    const openMonthlyBlockedModal = () => {
        monthlyBlockedCalendarMonth = (() => {
            const base = monthlyStartDateInput.value || bookingDateInput.value || todayValue;
            const date = new Date(`${base}T00:00:00`);
            return new Date(date.getFullYear(), date.getMonth(), 1);
        })();
        renderMonthlyBlockedModal();
        monthlyBlockedModal.classList.remove('hidden');
        document.body.classList.add('modal-open');
    };

    const ensureMonthlySelectionStillAvailable = async () => {
        if (!roomSelect.value || !bookingDateInput.value || !bookingEndDateInput.value) {
            return false;
        }

        await fetchUnavailableDates();

        if (hasBlockedDatesInRange(bookingDateInput.value, bookingEndDateInput.value)) {
            monthlyPlanInput.value = '';
            monthlyContinue.disabled = true;
            monthlyPlanDescription.textContent = 'The selected stay dates were just booked by another customer. Please choose another date range.';
            quoteMeta.textContent = 'This room is no longer available for the selected stay period.';
            updateMonthlyBlockedNote();
            openMonthlyBlockedModal();
            return false;
        }

        try {
            await fetchMonthlyQuote();
            return true;
        } catch (error) {
            currentQuote = null;
            monthlyPlanInput.value = '';
            monthlyContinue.disabled = true;
            monthlyPlanDescription.textContent = 'The selected stay dates are no longer available. Please choose another date range.';
            quoteMeta.textContent = 'This room is no longer available for the selected stay period.';
            updateMonthlyBlockedNote();
            openMonthlyBlockedModal();
            return false;
        }
    };

    const ensureScheduleCartStillAvailable = async () => {
        if (!scheduleCart.length) {
            return false;
        }

        const itemsByDate = scheduleCart.reduce((carry, item) => {
            const key = item.booking_date;
            carry.set(key, [...(carry.get(key) || []), item]);
            return carry;
        }, new Map());
        const unavailableKeys = new Set();

        for (const [bookingDate, items] of itemsByDate.entries()) {
            const data = await fetchJson(`${layoutUrl}?booking_date=${encodeURIComponent(bookingDate)}`);
            const rooms = Array.isArray(data.rooms) ? data.rooms : [];
            const availableKeys = new Set();

            rooms.forEach((room) => {
                const slots = Array.isArray(room.available_slots) ? room.available_slots : [];

                slots.forEach((slot) => {
                    availableKeys.add(scheduleItemKey({
                        hyve_room_id: room.id,
                        booking_date: bookingDate,
                        start_time: slot.value,
                        end_time: slot.end_time,
                    }));
                });
            });

            items.forEach((item) => {
                const key = scheduleItemKey(item);

                if (!availableKeys.has(key)) {
                    unavailableKeys.add(key);
                }
            });
        }

        if (!unavailableKeys.size) {
            return true;
        }

        scheduleCart = scheduleCart.filter((item) => !unavailableKeys.has(scheduleItemKey(item)));
        syncScheduleItemsInput();
        updateScheduleSelection();
        updateCheckoutSummary();

        const removedCount = unavailableKeys.size;
        messageBody.textContent = `${removedCount} selected schedule ${removedCount === 1 ? 'slot is' : 'slots are'} no longer available because the room was already booked. Please review your cart and choose another time.`;
        window.alert(`Some selected schedule slots are already booked and were removed from your cart.`);
        return false;
    };

    const resolveLongStayPlan = (roomId = roomSelect.value) => {
        const options = roomId ? getMonthlyOptionsForRoom(roomId) : [];

        if (!options.length) {
            return null;
        }

        const recommendedType = recommendedLongStayType();
        return options.find((option) => option.type === recommendedType) || options[0];
    };

    const getLongStayOptionsForRoom = (roomId = roomSelect.value) => roomId ? getMonthlyOptionsForRoom(roomId) : [];

    const monthlyRangeNeedsUseType = () => {
        const { startValue, endValue } = selectedLongStayRange();

        if (!roomSelect.value || !startValue || !endValue) {
            return false;
        }

        const options = getLongStayOptionsForRoom(roomSelect.value);
        if (!options.length) {
            return false;
        }

        const hasUseTypeOptions = options.some((option) => ['daily', 'weekly'].includes(option.type));

        if (!hasUseTypeOptions) {
            return false;
        }

        return !isWholeCalendarMonthStay(startValue, endValue);
    };

    const syncLongStayUseSelection = () => {
        const activeType = longStayUseTypeInput.value || '';

        longStayUseChoices.forEach((button) => {
            button.classList.toggle('is-active', button.dataset.longStayUseChoice === activeType);
        });

        if (activeType) {
            monthlySummaryUseType.textContent = activeType === 'night'
                ? 'Night Use (8:00 PM - 8:00 AM)'
                : 'Day Use (8:00 AM - 8:00 PM)';
            monthlySummaryUseTypeRow.classList.remove('hidden');
            return;
        }

        monthlySummaryUseType.textContent = '--';
        monthlySummaryUseTypeRow.classList.add('hidden');
    };

    const longStayUsePrompt = () => {
        if (longStayUseTypeInput.value === 'night') {
            return 'Night Use selected. HYVE will use the 8:00 PM to 8:00 AM rate window for each covered day.';
        }

        if (longStayUseTypeInput.value === 'day') {
            return 'Day Use selected. HYVE will use the 8:00 AM to 8:00 PM rate window for each covered day.';
        }

        return 'Choose Day Use or Night Use first so HYVE can compute the correct long-stay total.';
    };

    const syncLongStayUseVisibility = () => {
        const needsUseType = monthlyRangeNeedsUseType();
        longStayUseWrap.classList.toggle('hidden', !needsUseType);

        if (!needsUseType) {
            longStayUseTypeInput.value = '';
        }

        syncLongStayUseSelection();

        return needsUseType;
    };

    const fetchMonthlyQuote = async () => {
        if (!roomSelect.value || !bookingDateInput.value || !bookingEndDateInput.value) {
            currentQuote = null;
            resetMonthlySummary();
            quoteTotal.textContent = 'Php 0.00';
            quoteMinimum.textContent = 'Php 0.00';
            quoteBalance.textContent = 'Php 0.00';
            quoteMeta.textContent = 'Select a room, then click your start date and end date to load the long-stay rate.';
            checkoutSubmit.textContent = 'Confirm & Pay Php 0.00';
            return;
        }

        const useTypeQuery = longStayUseTypeInput.value ? `&long_stay_use_type=${encodeURIComponent(longStayUseTypeInput.value)}` : '';
        const data = await fetchJson(`${quoteUrl}?booking_mode=monthly&hyve_room_id=${encodeURIComponent(roomSelect.value)}&booking_date=${encodeURIComponent(bookingDateInput.value)}&booking_end_date=${encodeURIComponent(bookingEndDateInput.value)}${useTypeQuery}`);

        currentQuote = data;
        monthlyPlanInput.value = data.monthly_plan_label || '';
        longStayUseTypeInput.value = data.long_stay_use_type || '';
        quoteTotal.textContent = formatCurrency(data.total_amount);
        quoteMinimum.textContent = formatCurrency(data.minimum_downpayment_amount);
        quoteMeta.textContent = `${data.rate_name} | ${data.charge_period_label} | ${data.unit_label || '--'} from ${formatDate(bookingDateInput.value)} to ${formatDate(data.booking_end_date || bookingEndDateInput.value)}.`;
        paymentInstructions.textContent = data.payment?.instructions || paymentInstructions.textContent;
        downpaymentInput.min = String(data.minimum_downpayment_amount);

        if (!downpaymentInput.value || Number(downpaymentInput.value) < Number(data.minimum_downpayment_amount)) {
            downpaymentInput.value = String(data.minimum_downpayment_amount);
        }

        monthlySummaryDate.textContent = formatDate(bookingDateInput.value);
        monthlySummaryEndDate.textContent = formatDate(data.booking_end_date || bookingEndDateInput.value);
        monthlySummaryPlan.textContent = data.monthly_plan_label || monthlyPlanInput.value;
        monthlySummaryUseType.textContent = data.long_stay_use_label || '--';
        monthlySummaryUseTypeRow.classList.toggle('hidden', !data.long_stay_use_label);
        monthlySummaryUnits.textContent = data.unit_label || '--';
        monthlySummaryTotal.textContent = formatCurrency(data.total_amount);
        monthlyPlanDescription.textContent = data.long_stay_use_label
            ? `Automatic breakdown applied: ${data.long_stay_use_label} - ${data.monthly_plan_label || '--'}. Review the total below, then continue to checkout.`
            : `Automatic breakdown applied: ${data.monthly_plan_label || '--'}. Review the total below, then continue to checkout.`;
        monthlyInlineSummary.classList.remove('hidden');
        monthlyContinue.disabled = false;
        syncLongStayUseSelection();
        updateBalance();
        updateCheckoutSummary();
    };

    const renderMonthlyPlanButtons = () => {
        return;

        return;

        const activeOption = resolveLongStayPlan(roomId);
        const recommendedOption = { label: null };
        options.forEach((option) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `booking-slot-pill${activeOption?.label === option.label ? ' is-active' : ''}`;
            button.dataset.planLabel = option.label;
            button.dataset.planType = option.type;
            button.innerHTML = `
                <strong>${option.label}${option.label === recommendedOption.label ? ' • Recommended' : ''}</strong>
                <span>${option.display_amount} / ${option.type}</span>
            `;
            button.addEventListener('click', async () => {
                monthlyManualPlanType = option.type;
                monthlyPlanInput.value = option.label;
                if (bookingDateInput.value) {
                    syncLongStayRangeToPlan();
                }
                renderMonthlyPlanButtons();
                await refreshMonthlySelection();
            });
            monthlyPlanButtons.append(button);
        });
    };

    const refreshMonthlySelection = async () => {
        const roomId = roomSelect.value;
        const options = roomId ? getMonthlyOptionsForRoom(roomId) : [];
        resetMonthlySummary();
        renderMonthlyPlanButtons();

        if (!roomId) {
            monthlyPlanInput.value = '';
            longStayUseTypeInput.value = '';
            syncLongStayUseVisibility();
            monthlyPlanDescription.textContent = 'Select a room first, then choose your start date and end date.';
            quoteMeta.textContent = 'Select a room first to start a long-stay booking.';
            return;
        }

        if (!options.length) {
            monthlyPlanInput.value = '';
            longStayUseTypeInput.value = '';
            syncLongStayUseVisibility();
            monthlyPlanDescription.textContent = 'This room does not have an active long-stay rate yet.';
            quoteMeta.textContent = 'No long-stay rates are available for this room yet.';
            return;
        }

        if (!bookingDateInput.value || !bookingEndDateInput.value) {
            monthlyPlanInput.value = '';
            monthlySelectingEnd = false;
            longStayUseTypeInput.value = '';
            syncLongStayUseVisibility();
            monthlyPlanDescription.textContent = 'Choose your start date first, then choose your end date. HYVE will automatically compute the full long-stay breakdown.';
            quoteMeta.textContent = 'Select your stay period first to load the long-stay quote.';
            return;
        }

        if (monthlySelectingEnd && bookingDateInput.value === bookingEndDateInput.value) {
            monthlyPlanInput.value = '';
            syncLongStayUseVisibility();
            monthlySummaryDate.textContent = formatDate(bookingDateInput.value);
            monthlySummaryEndDate.textContent = '--';
            monthlySummaryPlan.textContent = '--';
            monthlySummaryUnits.textContent = '--';
            monthlySummaryTotal.textContent = 'Php 0.00';
            monthlyContinue.disabled = true;
            quoteTotal.textContent = 'Php 0.00';
            quoteMinimum.textContent = 'Php 0.00';
            quoteBalance.textContent = 'Php 0.00';
            quoteMeta.textContent = 'Start date selected. Choose your end date to load the long-stay quote.';
            monthlyPlanDescription.textContent = `Start date selected: ${formatDate(bookingDateInput.value)}. Choose your end date next so HYVE can compute the total.`;
            return;
        }

        if (hasBlockedDatesInRange(bookingDateInput.value, bookingEndDateInput.value)) {
            monthlyPlanInput.value = '';
            monthlySelectingEnd = true;
            syncLongStayUseVisibility();
            monthlyPlanDescription.textContent = 'The selected stay includes one or more fully booked dates. Please choose another date range.';
            quoteMeta.textContent = 'Your selected date range includes fully booked dates.';
            monthlyContinue.disabled = true;
            updateMonthlyBlockedNote();
            return;
        }

        const resolvedPlan = resolveLongStayPlan(roomId);

        if (!resolvedPlan) {
            monthlyPlanInput.value = '';
            return;
        }

        const needsUseType = syncLongStayUseVisibility();
        monthlySummaryDate.textContent = formatDate(bookingDateInput.value);
        monthlySummaryEndDate.textContent = formatDate(bookingEndDateInput.value);
        monthlySummaryUnits.textContent = `${longStayDayCount()} day${longStayDayCount() === 1 ? '' : 's'}`;
        monthlySelectingEnd = false;
        monthlyPlanDescription.textContent = needsUseType
            ? `Selected stay: ${formatDate(bookingDateInput.value)} to ${formatDate(bookingEndDateInput.value)}. ${longStayUsePrompt()}`
            : `Selected stay: ${formatDate(bookingDateInput.value)} to ${formatDate(bookingEndDateInput.value)}. HYVE will automatically compute the best long-stay breakdown.`;

        if (needsUseType && !longStayUseTypeInput.value) {
            monthlyPlanInput.value = '';
            monthlyContinue.disabled = true;
            quoteTotal.textContent = 'Php 0.00';
            quoteMinimum.textContent = 'Php 0.00';
            quoteBalance.textContent = 'Php 0.00';
            quoteMeta.textContent = 'Choose Day Use or Night Use first to load the correct long-stay quote.';
            return;
        }

        try {
            await fetchMonthlyQuote();
            messageBody.textContent = 'Long-stay booking summary is ready. Review the payment details before submitting.';
        } catch (error) {
            currentQuote = null;
            resetMonthlySummary();
            quoteTotal.textContent = 'Php 0.00';
            quoteMinimum.textContent = 'Php 0.00';
            quoteBalance.textContent = 'Php 0.00';
            quoteMeta.textContent = 'Unable to load the long-stay quote right now. Please try another room or date range.';
            checkoutSubmit.textContent = 'Confirm & Pay Php 0.00';
            updateMonthlyBlockedNote();
        }
    };

    const renderMonthlyCalendar = () => {
        monthlyCalendarTitle.textContent = formatMonthTitle(monthlyCalendarMonth);
        monthlyCalendarDays.innerHTML = '';

        const firstDay = new Date(monthlyCalendarMonth.getFullYear(), monthlyCalendarMonth.getMonth(), 1);
        const lastDay = new Date(monthlyCalendarMonth.getFullYear(), monthlyCalendarMonth.getMonth() + 1, 0);
        const leading = firstDay.getDay();

        for (let i = 0; i < leading; i += 1) {
            const filler = document.createElement('span');
            filler.className = 'booking-calendar-day is-filler';
            monthlyCalendarDays.append(filler);
        }

        const selectedStart = bookingDateInput.value || '';
        const selectedEnd = bookingEndDateInput.value || selectedStart;

        for (let day = 1; day <= lastDay.getDate(); day += 1) {
            const date = new Date(monthlyCalendarMonth.getFullYear(), monthlyCalendarMonth.getMonth(), day);
            const value = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'booking-calendar-day';
            button.textContent = String(day);
            const isBlocked = blockedDates.has(value);
            const isBeforeToday = value < todayValue;
            const roomMissing = !roomSelect.value;

            if (value === todayValue) {
                button.classList.add('is-today');
            }

            if (selectedStart && selectedEnd && value >= selectedStart && value <= selectedEnd) {
                button.classList.add('is-selected');
            }

            if (value === selectedStart || value === selectedEnd) {
                button.classList.add('is-range-edge');
            }

            if (isBlocked) {
                button.classList.add('is-booked');
            }

            if (isBeforeToday || isBlocked || roomMissing) {
                button.disabled = true;
            }

            button.addEventListener('click', async () => {
                if (!roomSelect.value) {
                    monthlyPlanDescription.textContent = 'Select a room first before choosing your stay dates.';
                    return;
                }

                if (!monthlyRangeAnchor || !monthlySelectingEnd) {
                    bookingDateInput.value = value;
                    bookingEndDateInput.value = value;
                    monthlyRangeAnchor = value;
                    monthlySelectingEnd = true;
                    monthlyPlanDescription.textContent = `Start date selected: ${formatDate(value)}. Click your end date next so HYVE can compute the total.`;
                } else {
                    const startValue = monthlyRangeAnchor <= value ? monthlyRangeAnchor : value;
                    const endValue = monthlyRangeAnchor <= value ? value : monthlyRangeAnchor;

                    if (hasBlockedDatesInRange(startValue, endValue)) {
                        bookingDateInput.value = value;
                        bookingEndDateInput.value = value;
                        monthlyRangeAnchor = value;
                        monthlySelectingEnd = true;
                        monthlyPlanDescription.textContent = 'That date range includes one or more fully booked days, so HYVE reset the selection. Pick another end date.';
                    } else {
                        bookingDateInput.value = startValue;
                        bookingEndDateInput.value = endValue;
                        monthlyRangeAnchor = endValue;
                        monthlySelectingEnd = false;
                    }
                }

                monthlyCalendarMonth = new Date(`${bookingDateInput.value}T00:00:00`);
                monthlyStartDateInput.value = bookingDateInput.value || '';
                monthlyEndDateInput.min = bookingDateInput.value || todayValue;
                monthlyEndDateInput.value = bookingEndDateInput.value || bookingDateInput.value || '';
                updateSlotHeading();
                renderMonthlyCalendar();
                await refreshMonthlySelection();
            });

            monthlyCalendarDays.append(button);
        }
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
            roomMeta.textContent = 'Choose a room first, then pick an available date and start time.';
            selectedRoomName.textContent = 'Choose a room';
            selectedRoomSpace.textContent = '';
            selectedRoomRate.textContent = 'Ask HYVE';
            monthlyRoomName.textContent = 'Choose a room';
            monthlyRoomSpace.textContent = '';
            monthlyRoomRate.textContent = 'Ask HYVE';
            checkoutRoom.textContent = 'Choose a room';
            return;
        }

        roomMeta.textContent = `${roomCard.dataset.roomDescription} | ${roomCard.dataset.roomSpace}`;
        selectedRoomName.textContent = roomCard.dataset.roomName || 'Choose a room';
        selectedRoomSpace.textContent = roomCard.dataset.roomSpace || '';
        selectedRoomRate.textContent = roomCard.dataset.roomRate || 'Ask HYVE';
        monthlyRoomName.textContent = roomCard.dataset.roomName || 'Choose a room';
        monthlyRoomSpace.textContent = roomCard.dataset.roomSpace || '';
        monthlyRoomRate.textContent = roomCard.dataset.roomRate || 'Ask HYVE';
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
            renderMonthlyCalendar();
            updateMonthlyBlockedNote();
            return;
        }

        const data = await fetchJson(`${unavailableDatesUrl}?hyve_room_id=${encodeURIComponent(roomSelect.value)}&horizon_days=${encodeURIComponent(horizonDays)}`);
        blockedDates = new Set((Array.isArray(data.unavailable_dates) ? data.unavailable_dates : []).map((item) => item.value));
        renderCalendar();
        renderMonthlyCalendar();
        updateMonthlyBlockedNote();
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
            durationDisplay.textContent = `Minimum booking is ${minimumDuration / 60} hours.`;
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
        summaryRate.innerHTML = buildRateBreakdownMarkup(data, startSelect.value, endSelect.value);
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
        const selectedItemKeys = new Set(scheduleCart.map((item) => scheduleItemKey(item)));
        const roomSlotsByLabel = new Map();
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
            const roomCard = roomCardMap.get(String(room.id));
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
            roomSlotsByLabel.set(String(room.id), new Map(
                availableSlots.map((slot) => [slot.label, slot]),
            ));
        });

        const bodyFragment = document.createDocumentFragment();

        rows.forEach((label) => {
            const tr = document.createElement('tr');
            const th = document.createElement('th');
            th.textContent = label;
            tr.append(th);

            rooms.forEach((room) => {
                const td = document.createElement('td');
                const availableSlot = roomSlotsByLabel.get(String(room.id))?.get(label) || null;
                const isPastCurrentDaySlot = isPastScheduleLabelForToday(label, data.booking_date);

                if (availableSlot && !isPastCurrentDaySlot) {
                    const button = document.createElement('button');
                    const roomCard = roomCardMap.get(String(room.id));
                    const itemKey = scheduleItemKey({
                        hyve_room_id: room.id,
                        booking_date: data.booking_date,
                        start_time: availableSlot.value,
                        end_time: availableSlot.end_time,
                    });
                    const isSelected = selectedItemKeys.has(itemKey);

                    button.type = 'button';
                    button.className = `booking-schedule__slot is-available${isSelected ? ' is-selected' : ''}`;
                    button.dataset.scheduleItemKey = itemKey;
                    button.dataset.roomRateLabel = availableSlot.display_amount || roomCard?.dataset.roomRate || 'Available';
                    button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
                    button.innerHTML = `
                        <span class="booking-schedule__slot-dot">${isSelected ? '&#10003;' : ''}</span>
                        <strong class="booking-schedule__slot-price">${availableSlot.display_amount || roomCard?.dataset.roomRate || 'Available'}</strong>
                    `;
                    button.addEventListener('click', () => {
                        if (button.disabled) {
                            return;
                        }

                        setBookingMode('schedule');

                        const currentItemKey = scheduleItemKey({
                            hyve_room_id: room.id,
                            booking_date: data.booking_date,
                            start_time: availableSlot.value,
                            end_time: availableSlot.end_time,
                        });
                        const alreadySelected = scheduleCart.some((item) => scheduleItemKey(item) === currentItemKey);

                        if (alreadySelected) {
                            syncScheduleSlotVisual(button, false);
                            removeScheduleItem(itemKey);
                            updateScheduleSelection();
                            updateCheckoutSummary();
                            return;
                        }

                        syncScheduleSlotVisual(button, true);

                        if (!scheduleCart.some((item) => scheduleItemKey(item) === currentItemKey)) {
                            scheduleCart.push({
                                hyve_room_id: room.id,
                                booking_date: data.booking_date,
                                start_time: availableSlot.value,
                                end_time: availableSlot.end_time,
                                room_name: room.room_name,
                                room_space: room.space_label || '',
                                label: availableSlot.label,
                                total_amount: Number(availableSlot.total_amount || 0),
                                breakdown: Array.isArray(availableSlot.breakdown) ? availableSlot.breakdown : [],
                            });
                        }

                        syncScheduleItemsInput();
                        renderScheduleCart();
                        revealScheduleCart();
                        updateScheduleSelection();
                        updateCheckoutSummary();
                        syncScheduleSlotVisual(button, true);
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

            bodyFragment.append(tr);
        });

        scheduleBody.append(bodyFragment);
        updateScheduleSelection();
        refreshScheduleGridSelections();
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
                if (bookingMode === 'monthly') {
                    await fetchUnavailableDates();
                    renderMonthlyCalendar();
                    await refreshMonthlySelection();
                    return;
                }

                await fetchUnavailableDates();
                await fetchStartTimes();

                if (bookingMode === 'schedule') {
                    await renderSchedule();
                }
            } catch (error) {
                messageBody.textContent = 'Unable to load room availability right now. Please try again.';
            }
        });

        card.addEventListener('keydown', async (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            if (event.target?.closest?.('[data-room-preview-open]')) {
                return;
            }

            event.preventDefault();
            card.click();
        });
    });

    roomPreviewTriggers.forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            const roomCard = trigger.closest('[data-room-card]');

            if (roomCard?.dataset.roomId) {
                setActiveRoom(roomCard.dataset.roomId);
            }

            openRoomPreview();
        });
    });

    roomPreviewClosers.forEach((closer) => {
        closer.addEventListener('click', closeRoomPreview);
    });

    roomPreviewModal?.addEventListener('click', (event) => {
        if (event.target === roomPreviewModal) {
            closeRoomPreview();
        }
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

            if (mode === 'monthly') {
                monthlyCalendarMonth = bookingDateInput.value
                    ? new Date(`${bookingDateInput.value}T00:00:00`)
                    : new Date(today.getFullYear(), today.getMonth(), 1);
                monthlyRangeAnchor = bookingDateInput.value || '';
                monthlySelectingEnd = true;
                renderMonthlyCalendar();
                await refreshMonthlySelection();
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
    downpaymentInput.addEventListener('input', () => updateBalance(false));
    downpaymentInput.addEventListener('blur', () => updateBalance(true));

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
    scheduleContinue.addEventListener('click', async () => {
        if (scheduleContinue.disabled) {
            return;
        }

        const removedExpiredSlots = pruneExpiredScheduleItems();

        if (removedExpiredSlots) {
            return;
        }

        const stillAvailable = await ensureScheduleCartStillAvailable();

        if (!stillAvailable) {
            return;
        }

        updateCheckoutSummary();
        showCheckout();
    });
    monthlyContinue.addEventListener('click', async () => {
        if (monthlyContinue.disabled) {
            return;
        }

        const stillAvailable = await ensureMonthlySelectionStillAvailable();

        if (!stillAvailable) {
            return;
        }

        updateCheckoutSummary();
        showCheckout();
    });
    monthlyBlockedOpen.addEventListener('click', () => {
        openMonthlyBlockedModal();
    });
    monthlyBlockedPrev.addEventListener('click', () => {
        monthlyBlockedCalendarMonth = new Date(monthlyBlockedCalendarMonth.getFullYear(), monthlyBlockedCalendarMonth.getMonth() - 1, 1);
        renderMonthlyBlockedModal();
    });
    monthlyBlockedNext.addEventListener('click', () => {
        monthlyBlockedCalendarMonth = new Date(monthlyBlockedCalendarMonth.getFullYear(), monthlyBlockedCalendarMonth.getMonth() + 1, 1);
        renderMonthlyBlockedModal();
    });
    monthlyCalendarPrev.addEventListener('click', () => {
        monthlyCalendarMonth = new Date(monthlyCalendarMonth.getFullYear(), monthlyCalendarMonth.getMonth() - 1, 1);
        renderMonthlyCalendar();
    });
    monthlyCalendarNext.addEventListener('click', () => {
        monthlyCalendarMonth = new Date(monthlyCalendarMonth.getFullYear(), monthlyCalendarMonth.getMonth() + 1, 1);
        renderMonthlyCalendar();
    });
    monthlyBlockedClosers.forEach((closer) => {
        closer.addEventListener('click', () => {
            closeMonthlyBlockedModal();
        });
    });
    monthlyBlockedModal.addEventListener('click', (event) => {
        if (event.target === monthlyBlockedModal) {
            closeMonthlyBlockedModal();
        }
    });

    form.addEventListener('submit', async (event) => {
        syncBookingEndDateForCurrentMode();

        if (bookingMode === 'monthly') {
            if (!monthlyPlanInput.value) {
                event.preventDefault();
                showPicker();
                await refreshMonthlySelection();
            }

            return;
        }

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

    monthlyStartDateInput.addEventListener('change', async () => {
        bookingDateInput.value = monthlyStartDateInput.value || '';
        monthlyEndDateInput.min = monthlyStartDateInput.value || todayValue;

        if (!monthlyStartDateInput.value) {
            bookingEndDateInput.value = '';
            monthlyEndDateInput.value = '';
            monthlyRangeAnchor = '';
            monthlySelectingEnd = false;
            await refreshMonthlySelection();
            return;
        }

        if (!monthlyEndDateInput.value || monthlyEndDateInput.value < monthlyStartDateInput.value) {
            monthlyEndDateInput.value = monthlyStartDateInput.value;
            bookingEndDateInput.value = monthlyStartDateInput.value;
            monthlySelectingEnd = true;
        } else {
            bookingEndDateInput.value = monthlyEndDateInput.value;
            monthlySelectingEnd = false;
        }

        monthlyRangeAnchor = monthlyStartDateInput.value;
        monthlyCalendarMonth = new Date(`${monthlyStartDateInput.value}T00:00:00`);
        await refreshMonthlySelection();
    });

    monthlyEndDateInput.addEventListener('change', async () => {
        bookingEndDateInput.value = monthlyEndDateInput.value || '';

        if (!monthlyEndDateInput.value) {
            monthlySelectingEnd = true;
            await refreshMonthlySelection();
            return;
        }

        if (monthlyStartDateInput.value && monthlyEndDateInput.value < monthlyStartDateInput.value) {
            monthlyEndDateInput.value = monthlyStartDateInput.value;
            bookingEndDateInput.value = monthlyStartDateInput.value;
        }

        monthlySelectingEnd = false;
        await refreshMonthlySelection();
    });

    longStayUseChoices.forEach((button) => {
        button.addEventListener('click', async () => {
            const useType = button.dataset.longStayUseChoice || '';
            longStayUseTypeInput.value = useType;
            syncLongStayUseSelection();
            monthlyPlanDescription.textContent = `Selected stay: ${formatDate(bookingDateInput.value)} to ${formatDate(bookingEndDateInput.value)}. ${longStayUsePrompt()}`;
            await refreshMonthlySelection();
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !monthlyBlockedModal.classList.contains('hidden')) {
            closeMonthlyBlockedModal();
        }

        if (event.key === 'Escape' && roomPreviewModal && !roomPreviewModal.classList.contains('hidden')) {
            closeRoomPreview();
        }
    });

    schedulePrev.addEventListener('click', async () => {
        await shiftScheduleDate(-1);
    });

    scheduleNext.addEventListener('click', async () => {
        await shiftScheduleDate(1);
    });

    setBookingMode(bookingMode);
    syncBookingEndDateForCurrentMode();
    updatePaymentDestination();
    syncGuestFullName();
    updateSlotHeading();
    updateRoomMeta();
    updateCheckoutSummary();
    updateScheduleSelection();
    renderCalendar();
    renderMonthlyCalendar();
    monthlyStartDateInput.value = bookingDateInput.value || '';
    monthlyEndDateInput.value = bookingEndDateInput.value || bookingDateInput.value || '';
    monthlyEndDateInput.min = monthlyStartDateInput.value || todayValue;
    syncLongStayUseVisibility();
    resetSlots();
    resetQuote();
    resetMonthlySummary();

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

        if (bookingMode === 'monthly') {
            monthlyCalendarMonth = bookingDateInput.value
                ? new Date(`${bookingDateInput.value}T00:00:00`)
                : new Date(today.getFullYear(), today.getMonth(), 1);
            monthlyRangeAnchor = bookingDateInput.value || '';
            monthlySelectingEnd = bookingDateInput.value === bookingEndDateInput.value;
            renderMonthlyCalendar();
            await refreshMonthlySelection();
        }
    });
};

const setupAgreementModals = () => {
    const modals = [...document.querySelectorAll('[data-agreement-modal]')];

    if (!modals.length) {
        document.querySelectorAll('form').forEach((form) => {
            const checkbox = form.querySelector('[data-agreement-checkbox]');
            const submit = form.querySelector('[data-agreement-submit]');

            if (!checkbox || !submit) {
                return;
            }

            const updateSubmitState = () => {
                submit.disabled = !checkbox.checked;
            };

            checkbox.addEventListener('change', updateSubmitState);
            updateSubmitState();
        });

        return;
    }

    const closeModal = (modal) => {
        modal.classList.add('hidden');
        document.body.classList.remove('modal-open');
    };

    const openModal = (modal) => {
        modal.classList.remove('hidden');
        document.body.classList.add('modal-open');
    };

    document.querySelectorAll('form').forEach((form) => {
        const checkbox = form.querySelector('[data-agreement-checkbox]');
        const submit = form.querySelector('[data-agreement-submit]');

        if (!checkbox || !submit) {
            return;
        }

        const updateSubmitState = () => {
            submit.disabled = !checkbox.checked;
        };

        checkbox.addEventListener('change', updateSubmitState);
        form.addEventListener('submit', (event) => {
            if (checkbox.checked) {
                return;
            }

            event.preventDefault();
            checkbox.focus();
        });
        updateSubmitState();
    });

    document.querySelectorAll('[data-agreement-open]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const modalId = trigger.getAttribute('data-agreement-open');
            const modal = modalId ? document.getElementById(modalId) : null;

            if (!modal) {
                return;
            }

            openModal(modal);
        });
    });

    modals.forEach((modal) => {
        modal.querySelectorAll('[data-agreement-close]').forEach((closer) => {
            closer.addEventListener('click', () => closeModal(modal));
        });

        modal.querySelector('[data-agreement-accept]')?.addEventListener('click', () => {
            const targetId = modal.id;
            const checkbox = targetId
                ? document.querySelector(`[data-agreement-open="${targetId}"]`)?.closest('form')?.querySelector('[data-agreement-checkbox]')
                : null;

            if (checkbox) {
                checkbox.checked = true;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            }

            closeModal(modal);
        });

        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal(modal);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        modals.forEach((modal) => {
            if (!modal.classList.contains('hidden')) {
                closeModal(modal);
            }
        });
    });
};

document.addEventListener('DOMContentLoaded', () => {
    setupNav();
    setupAmenitiesGallery();
    setupAdminRoomModals();
    setupMemberMenu();
    setupMemberBookingsTabs();
    setupMemberBookingModal();
    setupAgreementModals();
    setupSpacesBrowser();
    setupReveal();
    setupBookingPageV2();
});
