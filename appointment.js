document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector('.appointment-form');
    const fullNameInput = document.getElementById('fullName');
    const emailInput = document.getElementById('email');
    const contactNumberInput = document.getElementById('contactNumber');
    const serviceSelect = document.getElementById('service');
    const consentCheckbox = document.getElementById('consent');

    // Custom Calendar Elements
    const customDatePicker = document.getElementById('customDatePicker');
    const hiddenDateInput = document.getElementById('appointmentDate');
    const dateDisplayBtn = document.getElementById('appointmentDateDisplay');
    const dateDisplayText = document.getElementById('appointmentDateText');
    const calendarPopover = document.getElementById('calendarPopover');
    const calPrevMonthBtn = document.getElementById('calPrevMonth');
    const calNextMonthBtn = document.getElementById('calNextMonth');
    const calMonthSelect = document.getElementById('calMonthSelect');
    const calYearSelect = document.getElementById('calYearSelect');
    const calDaysGrid = document.getElementById('calDaysGrid');

    // Pop-up Elements
    const popup = document.getElementById('restrictionPopup');
    const popupTitle = document.getElementById('restrictionPopupTitle');
    const popupMsg = document.getElementById('restrictionPopupMsg');
    const closePopupBtn = document.getElementById('closeRestrictionPopup');
    let popupTimeout;

    function showRestrictionPopup(message, targetEl = null, title = "Input Restriction") {
        if (!popup) return;
        if (!popupMsg) return;
        if (!popupTitle) return;

        popupTitle.textContent = title;
        popupMsg.textContent = message;
        popup.classList.add('show');

        if (targetEl) {
            targetEl.classList.remove('input-error');
            void targetEl.offsetWidth;
            targetEl.classList.add('input-error');
            setTimeout(() => targetEl.classList.remove('input-error'), 1500);
        }

        clearTimeout(popupTimeout);
        popupTimeout = setTimeout(() => {
            popup.classList.remove('show');
        }, 3800);
    }

    if (closePopupBtn && popup) {
        closePopupBtn.addEventListener('click', () => {
            popup.classList.remove('show');
            clearTimeout(popupTimeout);
        });
    }

    // ========================================================
    // Custom Calendar: Shows ONLY Current Year and Next Year
    // ========================================================
    const now = new Date();
    const currentYear = now.getFullYear();
    const maxAllowedYear = currentYear + 1;
    const currentMonth = now.getMonth();
    const currentDay = now.getDate();

    const monthNames = [
        "January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"
    ];
    const shortMonthNames = [
        "Jan", "Feb", "Mar", "Apr", "May", "Jun",
        "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"
    ];

    let viewYear = currentYear;
    let viewMonth = currentMonth;

    function formatIsoDate(year, monthIndex, day) {
        const m = String(monthIndex + 1).padStart(2, '0');
        const d = String(day).padStart(2, '0');
        return `${year}-${m}-${d}`;
    }

    function formatDisplayDate(year, monthIndex, day) {
        return `${shortMonthNames[monthIndex]} ${day}, ${year}`;
    }

    function populateYearAndMonthControls() {
        if (!calYearSelect) return;
        if (!calMonthSelect) return;

        // Only add current year and next year so past/far-future years are never visible
        calYearSelect.innerHTML = '';
        const allowedYears = [currentYear, maxAllowedYear];
        allowedYears.forEach((yr) => {
            const opt = document.createElement('option');
            opt.value = String(yr);
            opt.textContent = String(yr);
            calYearSelect.appendChild(opt);
        });

        calMonthSelect.innerHTML = '';
        monthNames.forEach((name, idx) => {
            const opt = document.createElement('option');
            opt.value = String(idx);
            opt.textContent = name;
            calMonthSelect.appendChild(opt);
        });
    }

    function renderCalendar() {
        if (!calDaysGrid) return;
        if (!calMonthSelect) return;
        if (!calYearSelect) return;

        calYearSelect.value = String(viewYear);

        // Hide past months in the current year so users cannot see or pick them
        Array.from(calMonthSelect.options).forEach((opt) => {
            const mIdx = Number(opt.value);
            const isPastMonth = (viewYear === currentYear && mIdx < currentMonth);
            opt.hidden = isPastMonth;
            opt.disabled = isPastMonth;
        });
        calMonthSelect.value = String(viewMonth);

        if (calPrevMonthBtn) {
            calPrevMonthBtn.disabled = (viewYear === currentYear && viewMonth === currentMonth);
        }
        if (calNextMonthBtn) {
            calNextMonthBtn.disabled = (viewYear === maxAllowedYear && viewMonth === 11);
        }

        calDaysGrid.innerHTML = '';

        const firstDayOfWeek = new Date(viewYear, viewMonth, 1).getDay();
        const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
        const selectedIso = hiddenDateInput ? hiddenDateInput.value : '';
        const todayIso = formatIsoDate(currentYear, currentMonth, currentDay);

        for (let i = 0; i < firstDayOfWeek; i++) {
            const emptyCell = document.createElement('span');
            emptyCell.className = 'cal-empty';
            calDaysGrid.appendChild(emptyCell);
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const dayBtn = document.createElement('button');
            dayBtn.type = 'button';
            dayBtn.className = 'cal-day';
            dayBtn.textContent = String(day);

            const cellDate = new Date(viewYear, viewMonth, day);
            const cellIso = formatIsoDate(viewYear, viewMonth, day);
            const isPastDay = (viewYear === currentYear && viewMonth === currentMonth && day < currentDay);
            const isThursday = (cellDate.getDay() === 4);

            if (cellIso === todayIso) {
                dayBtn.classList.add('is-today');
            }
            if (cellIso === selectedIso) {
                dayBtn.classList.add('is-selected');
            }

            if (isPastDay) {
                dayBtn.classList.add('is-disabled');
                dayBtn.addEventListener('click', () => {
                    showRestrictionPopup(
                        "You cannot select a past date. Please choose today or an upcoming date.",
                        dateDisplayBtn,
                        "Invalid Date"
                    );
                });
            } else if (isThursday) {
                dayBtn.classList.add('is-closed');
                dayBtn.title = "Clinic closed on Thursdays";
                dayBtn.addEventListener('click', () => {
                    showRestrictionPopup(
                        "Cebanico Dental Clinic is closed on Thursdays. Please choose another day.",
                        dateDisplayBtn,
                        "Clinic Closed on Thursdays"
                    );
                });
            } else {
                dayBtn.addEventListener('click', () => {
                    if (hiddenDateInput) {
                        hiddenDateInput.value = cellIso;
                    }
                    if (dateDisplayText) {
                        dateDisplayText.textContent = formatDisplayDate(viewYear, viewMonth, day);
                        dateDisplayText.classList.remove('date-placeholder');
                    }
                    closeCalendar();
                });
            }

            calDaysGrid.appendChild(dayBtn);
        }
    }

    function openCalendar() {
        if (!calendarPopover) return;
        if (!dateDisplayBtn) return;

        renderCalendar();

        // Check viewport space so the calendar always fits on screen without scrolling
        const btnRect = dateDisplayBtn.getBoundingClientRect();
        const spaceAbove = btnRect.top;
        const spaceBelow = window.innerHeight - btnRect.bottom;

        if (spaceAbove < 290 && spaceBelow > spaceAbove) {
            calendarPopover.classList.add('open-down');
        } else {
            calendarPopover.classList.remove('open-down');
        }

        calendarPopover.classList.add('open');
        dateDisplayBtn.setAttribute('aria-expanded', 'true');
    }

    function closeCalendar() {
        if (!calendarPopover) return;
        if (!dateDisplayBtn) return;
        calendarPopover.classList.remove('open');
        dateDisplayBtn.setAttribute('aria-expanded', 'false');
    }

    if (customDatePicker && dateDisplayBtn && calendarPopover) {
        populateYearAndMonthControls();
        renderCalendar();

        dateDisplayBtn.addEventListener('click', () => {
            if (calendarPopover.classList.contains('open')) {
                closeCalendar();
            } else {
                openCalendar();
            }
        });

        if (calYearSelect) {
            calYearSelect.addEventListener('change', () => {
                viewYear = Number(calYearSelect.value);
                if (viewYear === currentYear && viewMonth < currentMonth) {
                    viewMonth = currentMonth;
                }
                renderCalendar();
            });
        }

        if (calMonthSelect) {
            calMonthSelect.addEventListener('change', () => {
                viewMonth = Number(calMonthSelect.value);
                renderCalendar();
            });
        }

        if (calPrevMonthBtn) {
            calPrevMonthBtn.addEventListener('click', () => {
                if (viewYear === currentYear && viewMonth === currentMonth) return;
                viewMonth -= 1;
                if (viewMonth < 0) {
                    viewMonth = 11;
                    viewYear -= 1;
                }
                renderCalendar();
            });
        }

        if (calNextMonthBtn) {
            calNextMonthBtn.addEventListener('click', () => {
                if (viewYear === maxAllowedYear && viewMonth === 11) return;
                viewMonth += 1;
                if (viewMonth > 11) {
                    viewMonth = 0;
                    viewYear += 1;
                }
                renderCalendar();
            });
        }

        document.addEventListener('click', (e) => {
            if (!customDatePicker.contains(e.target)) {
                closeCalendar();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeCalendar();
            }
        });
    }

    // ========================================================
    // Field Input Restrictions & Pop-up Warnings
    // ========================================================
    if (form && fullNameInput && emailInput && contactNumberInput) {

        // 1. Full Name Field: Only allow letters and spaces
        fullNameInput.addEventListener('input', function () {
            const raw = this.value;
            if (/[0-9]/.test(raw)) {
                showRestrictionPopup(
                    "You cannot type numbers in this field. Please input your name.",
                    this,
                    "Full Name Restriction"
                );
            } else if (/[^A-Za-z\s]/.test(raw)) {
                showRestrictionPopup(
                    "Special characters are not allowed in this field. Please input your name using letters only.",
                    this,
                    "Full Name Restriction"
                );
            }
            this.value = raw.replace(/[^A-Za-z\s]/g, '');
        });

        // 2. Email Field: Only allow letters, numbers, @, _, ., and -
        emailInput.addEventListener('input', function () {
            const raw = this.value;
            if (/\s/.test(raw)) {
                showRestrictionPopup(
                    "Spaces are not allowed in the email field. Please input a valid email address.",
                    this,
                    "Email Restriction"
                );
            } else if (/[^A-Za-z0-9@_.-]/.test(raw)) {
                showRestrictionPopup(
                    "Special characters like that are not allowed in the email field.",
                    this,
                    "Email Restriction"
                );
            }
            this.value = raw.replace(/[^A-Za-z0-9@_.-]/g, '');
        });

        // 3. Contact Number Field: Must start with 09, numbers only, 11 digits max
        contactNumberInput.addEventListener('input', function () {
            const raw = this.value;

            if (/[A-Za-z]/.test(raw)) {
                showRestrictionPopup(
                    "You cannot type letters in the contact number field. Please enter numbers only.",
                    this,
                    "Contact Number Restriction"
                );
            } else if (/[^0-9]/.test(raw)) {
                showRestrictionPopup(
                    "Special characters and spaces are not allowed. Please enter numbers only.",
                    this,
                    "Contact Number Restriction"
                );
            }

            let digitsOnly = raw.replace(/[^0-9]/g, '');

            // Convert pasted +639XXXXXXXXX into 09XXXXXXXXX automatically
            if (digitsOnly.length === 12 && digitsOnly.startsWith('639')) {
                digitsOnly = '0' + digitsOnly.slice(2);
            }

            // Block any starting digits other than 09
            if (digitsOnly.length >= 1 && digitsOnly.charAt(0) !== '0') {
                showRestrictionPopup(
                    "Philippine mobile numbers must start with 09 (e.g., 09171234567).",
                    this,
                    "Contact Number Prefix Restriction"
                );
                digitsOnly = '';
            } else if (digitsOnly.length >= 2 && digitsOnly.substring(0, 2) !== '09') {
                showRestrictionPopup(
                    "Philippine mobile numbers must start with 09 (e.g., 09171234567).",
                    this,
                    "Contact Number Prefix Restriction"
                );
                digitsOnly = '0';
            }

            // Enforce 11-digit limit
            if (digitsOnly.length > 11) {
                showRestrictionPopup(
                    "Philippine mobile numbers must be 11 digits (e.g., 09171234567).",
                    this,
                    "Contact Number Limit"
                );
                digitsOnly = digitsOnly.slice(0, 11);
            }

            this.value = digitsOnly;
        });

        // 4. Form Submission Validation
        form.addEventListener('submit', function (e) {
            if (fullNameInput.value.trim().length < 2) {
                e.preventDefault();
                fullNameInput.focus();
                showRestrictionPopup(
                    "Please input your full name (at least 2 letters).",
                    fullNameInput,
                    "Full Name Required"
                );
                return;
            }

            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(emailInput.value.trim())) {
                e.preventDefault();
                emailInput.focus();
                showRestrictionPopup(
                    "Please enter a valid email address (e.g., name@example.com).",
                    emailInput,
                    "Invalid Email Address"
                );
                return;
            }

            if (!/^09[0-9]{9}$/.test(contactNumberInput.value)) {
                e.preventDefault();
                contactNumberInput.focus();
                showRestrictionPopup(
                    "Please enter a valid 11-digit Philippine mobile number starting with 09 (e.g., 09171234567).",
                    contactNumberInput,
                    "Invalid Contact Number"
                );
                return;
            }

            if (!serviceSelect) {
                e.preventDefault();
                return;
            }
            if (serviceSelect.value === "") {
                e.preventDefault();
                serviceSelect.focus();
                showRestrictionPopup(
                    "Please select the type of dental service you want to book.",
                    serviceSelect,
                    "Service Required"
                );
                return;
            }

            if (!hiddenDateInput) {
                e.preventDefault();
                return;
            }
            if (hiddenDateInput.value === "") {
                e.preventDefault();
                if (dateDisplayBtn) dateDisplayBtn.focus();
                openCalendar();
                showRestrictionPopup(
                    "Please select your preferred day of appointment.",
                    dateDisplayBtn,
                    "Appointment Date Required"
                );
                return;
            }

            if (!consentCheckbox) {
                e.preventDefault();
                return;
            }
            if (!consentCheckbox.checked) {
                e.preventDefault();
                consentCheckbox.focus();
                showRestrictionPopup(
                    "Please check the consent box so we can contact you about this appointment.",
                    consentCheckbox,
                    "Consent Required"
                );
                return;
            }
        });
    }
});